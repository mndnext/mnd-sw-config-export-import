<?php
/**
 * Plugin for shopware shops. Enables export and import of shopware configuration data
 * from database to files. Primary purpose is to make shopware config manageable
 * to version control systems like git. Also useful for deployment and backup purposes.
 *
 * Copyright (C) 2015-2018 MND Next GmbH <info@mndnext.de>
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as
 * published by the Free Software Foundation, either version 3 of the
 * License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program.  If not, see <https://www.gnu.org/licenses/>.
 */

namespace ShopwarePlugins\MndConfigExportImport\Commands;

use Shopware\Models\Form\Field;
use Shopware\Models\Form\Form;
use ShopwarePlugins\MndConfigExportImport\Components\MndConfigImporter;
use ShopwarePlugins\MndConfigExportImport\Components\XMLImporter;
use ShopwarePlugins\MndConfigExportImport\Exception\MismatchedVersionException;
use ShopwarePlugins\MndConfigExportImport\Exception\WrongRootException;

use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Finder\Finder;

/**
 * Class ImportFormCommand
 * @package ShopwarePlugins\MndConfigExportImport\Commands
 */
class ImportFormCommand extends MndImportCommand
{
    /**
     * {@inheritdoc}
     */
    protected function configure()
    {
        parent::configure();

        $this
            ->setDescription('Import forms from directory ' . $this->getDefaultPath() . ' or custom source via -p');
    }

    /**
     * {@inheritdoc}
     */
    public function getCommandName() {
        return "form";
    }

    /**
     * @param XMLImporter $importer
     * @param Form $form
     */
    private function readFormAttributes($importer, $form) {
        if ($importer->cursorInto('Attributes')) {
            $attributes = [];
            foreach ($importer->getList('Attribute') as $xmlAttribute) {
                $attributes[$xmlAttribute['key']] = $xmlAttribute;
            }
            if ($attributes) {
                $form->setAttribute($attributes);
            }
            $importer->cursorOut();
        }
    }

    /**
     * @param XMLImporter $importer
     * @param Form $form
     */
    private function readFields($importer, $form) {
        $formFieldRepository = Shopware()->Models()->getRepository('Shopware\Models\Form\Field');

        foreach ($importer->getList('Field') as $xmlField) {
            $importer->changeCursor($xmlField);
            $field = $formFieldRepository->findOneBy(
                array(
                    "name" => $xmlField['name'],
                    "formId" => $form->getId()
                )
            );

            if(empty($field)){
                $field = new Field();
                $field->setForm($form);
                $field->setName($xmlField['name']);
            }

            $field->setRequired((bool) (string) $importer->getAttribute('', 'required'));
            $field->setPosition((int) $importer->getAttribute('', 'position'));
            $importer->setProp($field, 'ErrorMsg');
            $importer->setProp($field, 'Note');
            $importer->setProp($field, 'Type');
            $importer->setProp($field, 'Label');
            $importer->setProp($field, 'Class');
            $importer->setProp($field, 'Value');
            $importer->setProp($field, 'TicketTask');

            $importer->cursorOut();
        }
    }


    /**
     * @param MndConfigImporter $importer
     */
    private function readForms($importer) {
        $formRepository = Shopware()->Models()->getRepository('Shopware\Models\Form\Form');
        foreach ($importer->getList('Form') as $xmlForm) {
            $importer->changeCursor($xmlForm);
            $form = $formRepository->findOneBy(
                array(
                    "id" => $importer->getAttribute('', 'id')
                )
            );

            if (empty($form)) {
                $form = new Form();
            }

            $form->setName($importer->getAttribute('', 'name'));
            $form->setIsocode($importer->getAttribute('', 'iso'));
            $form->setTicketTypeid($importer->getAttribute('', 'tickettype'));

            //$form->setText($importer->getText('Header'));
            $form->setText($importer->readFile('Header'));
            //$form->setText2($importer->getText('Confirmation'));
            $form->setText2($importer->readFile('Confirmation'));

            $form->setEmail($importer->getAttribute('Email', 'address'));
            $form->setEmailSubject($importer->getAttribute('Email', 'subject'));
            //$form->setEmailTemplate($importer->getText('Email'));
            $form->setEmailTemplate($importer->readFile('Template'));

            $form->setMetaTitle($importer->getAttribute('Meta', 'title'));
            $form->setMetaKeywords($importer->getAttribute('Meta', 'keywords'));
            $form->setMetaDescription($importer->getText('Meta'));

            if ($importer->cursorInto('LimitTo')) {
                $shopIds = '';
                foreach ($importer->getList('Shop') as $xmlShop) {
                    $importer->changeCursor($xmlShop);
                    $shopId = $importer->getAttribute('', 'id');

                    if ($shopIds) {
                        $shopIds .= '|';
                    }
                    $shopIds .= $shopId;
                    $importer->cursorOut();
                }
                if ($shopIds) {
                    $form->setShopIds($shopIds);
                }
                $importer->cursorOut();
            }

            $this->readFields($importer, $form);
            $this->readFormAttributes($importer, $form);

            Shopware()->Models()->persist($form);
            Shopware()->Models()->flush();

            $this->printMessage("form imported: <comment>" . $form->getName() . "</comment>");
            $importer->cursorOut();
        }
    }

    /**
     * {@inheritdoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        parent::execute($input, $output);

        $finder = new Finder();

        /** @var Finder[] $files */
        $files = $finder->files()->in($this->path)->name("*." . self::CONFIG_FILE);

        /** @var  \Symfony\Component\Finder\SplFileInfo $file */
        foreach ($files as $file) {

            try {
                $importer = new MndConfigImporter($file->getPathname());
                $this->printInfo("\nimport file " . $file->getPathname());
                $this->readForms($importer);
            } catch (WrongRootException $e) {
                // wrong root tag
            } catch (MismatchedVersionException $e) {
                // wrong version of plugin or shopware
                $this->printError('unmatched version! skipped file ' . $file->getPathname());
            }

        }
    }
}