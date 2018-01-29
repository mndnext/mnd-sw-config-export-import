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
use ShopwarePlugins\MndConfigExportImport\Components\MndConfigExporter;
use ShopwarePlugins\MndConfigExportImport\Components\XMLExporter;
use \Doctrine\Common\Util\Debug;

use ShopwarePlugins\MndConfigExportImport\Utils\MndConfigIO;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Class ExportFormCommand
 * @package Shopware\Plugin\MndConfigExportImport\Commands
 */
class ExportFormCommand extends MndExportCommand
{
    /**
     * {@inheritdoc}
     */
    protected function configure()
    {
        parent::configure();

        $this
            ->setName('mnd:sw'.$this->getCommandName().':export')
            ->setDescription('Export form pages to directory ' . $this->getDefaultPath() . ' or custom target via -p');
    }

    /**
     * {@inheritdoc}
     */
    public function getCommandName()
    {
        return "form";
    }

    function getErrors()
    {
        return $this->manager->getErrors();
    }

    /**
     * @param XMLExporter $exporter
     * @param Form $form
     */
    private function writeFields($exporter, $form) {
        /* @var Field $field */
        foreach ($form->getFields() as $field) {
            $exporter->newElement('Field');
            $exporter->addAttribute('name', $field->getName());
            $exporter->addAttribute('required', (int) $field->getRequired());
            $exporter->addAttribute('position', $field->getPosition());
            $exporter->addProp($field, 'ErrorMsg');
            $exporter->addProp($field, 'Note');
            $exporter->addProp($field, 'Type');
            $exporter->addProp($field, 'Label');
            $exporter->addProp($field, 'Class');
            $exporter->addProp($field, 'Value');
            $exporter->addProp($field, 'TicketTask');
        }
    }


    /**
     * @param MndConfigIO $io
     * @param Form $form
     */
    private function writeForm($io, $form) {
        $filename_base = preg_replace('/[^a-zA-Z0-9]/', '', $form->getName());
        $io->setSubPath($form->getId() . '_' . $filename_base);

        $exporter = new MndConfigExporter('Form');
        $exporter->addAttribute('id', $form->getId());
        $exporter->addAttribute('name', $form->getName());
        $exporter->addAttribute('iso', $form->getIsocode());
        $exporter->addAttribute('tickettype', $form->getTicketTypeid());

        $exporter->newElement('Email');
        $exporter->addAttribute('address', $form->getEmail());
        $exporter->addAttribute('subject', $form->getEmailSubject());

        $filename = $filename_base . '_template.html';
        $exporter->newElement('Template');
        $exporter->addAttribute('file', $filename);
        $this->write($io, $filename, $form->getEmailTemplate());
        //$exporter->setText($form->getEmailTemplate(), true);

        $filename = $filename_base . '_header.html';
        $exporter->newElement('Header');
        $exporter->addAttribute('file', $filename);
        $this->write($io, $filename, $form->getText());
        //$exporter->setText($form->getText(), true);

        $filename = $filename_base . '_confirmation.html';
        $exporter->newElement('Confirmation');
        $exporter->addAttribute('file', $filename);
        $this->write($io, $filename, $form->getText2());
        //$exporter->setText($form->getText2(), true);

        $exporter->finishElement();

        //if ($form->getMetaTitle() || $form->getMetaKeywords() || $form->getMetaDescription()) {
            $exporter->newElement('Meta');
            $exporter->addAttribute('title', $form->getMetaTitle());
            $exporter->addAttribute('keywords', $form->getMetaKeywords());
            $exporter->setText($form->getMetaDescription(), true);
            $exporter->finishElement();
        //}

        if ($form->getShopIds()) {
            $exporter->startList('LimitTo');
            $shopIds = explode('|', $form->getShopIds());
            foreach ($shopIds as $shopId) {
                $exporter->add('Shop', '', false, ['id' => $shopId]);
            }
            $exporter->finishList();
        }

        $this->writeFields($exporter, $form);

        if (null != $form->getAttribute()) {
            $attributes = get_object_vars(Debug::export($form->getAttribute(), 1));
            $exporter->startList('Attributes');
            foreach ($attributes as $key => $value) {
                $exporter->newElement('Attribute');
                $exporter->addAttribute('key', $key);
                $exporter->setText($value);
            }
            $exporter->finishList();
        }

        $filename = $filename_base . '.' . self::CONFIG_FILE;
        $this->write($io, $filename, $exporter->get(), true);
    }
    
    
    /**
     * {@inheritdoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        parent::execute($input, $output);

        $formRepository = Shopware()->Models()->getRepository('Shopware\Models\Form\Form');
        try {
            $io = new MndConfigIO($this->getDefaultPath(), $input->getOptions());
            $io->setModulePath($this->getCommandName());
            foreach ($formRepository->findAll() as $form) {
                $this->writeForm($io, $form);
            }
        } catch (\Exception $e) {
            $this->printError($e->getMessage());
        }
    }
}