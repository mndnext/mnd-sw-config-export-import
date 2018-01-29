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

use Doctrine\Common\Collections\ArrayCollection;
use Shopware\Models\Shop\Template;
use Shopware\Models\Shop\TemplateConfig\Element;
use Shopware\Models\Shop\TemplateConfig\Layout;
use Shopware\Models\Shop\TemplateConfig\Set;
use ShopwarePlugins\MndConfigExportImport\Components\MndConfigImporter;
use ShopwarePlugins\MndConfigExportImport\Components\XMLImporter;

use ShopwarePlugins\MndConfigExportImport\Exception\MismatchedVersionException;
use ShopwarePlugins\MndConfigExportImport\Exception\WrongRootException;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Finder\Finder;

/**
 * Class ImportThemeConfigCommand
 * @package Shopware\Plugin\MndConfigExportImport\Commands
 */
class ImportThemeConfigCommand extends MndImportCommand
{
    /**
     * {@inheritdoc}
     */
    protected function configure() {
        parent::configure();

        $this
            ->setName('mnd:sw'.$this->getCommandName().':import')
            ->setDescription('Export Theme Config to directory ' . $this->getDefaultPath() . ' or custom target via -p');
    }

    /**
     * {@inheritdoc}
     */
    public function getCommandName() {
        return "themeconfig";
    }

    /**
     * @param XMLImporter $importer
     * @param $xmlSets
     * @param Template $template
     * @return Set[]
     */
    private function readConfigSets($importer, $xmlSets, $template) {
        $setRepository = Shopware()->Models()->getRepository('Shopware\Models\Shop\TemplateConfig\Set');

        $sets = [];
        foreach($xmlSets->Set AS $xmlSet){
            $file = $importer->changeCursor($xmlSet, true);
            if (gettype($file) == 'string') {
                $this->printNote('separate xml file loaded: ' . $file);
            }
            $set = $setRepository->findOneBy(array(
                "templateId" => $template->getId(),
                "name" => $importer->getAttribute('', 'name')
            ));

            if(!$set){
                $set = new Set();
                $set->setName($importer->getAttribute('', 'name'));
            }

            $set->setTemplate($template);
            $set->setDescription($importer->getAttribute('', 'description'));
            $set->setValues($importer->getArray('Value'));

            $sets[] = $set;
            $importer->cursorOut();
        }
        return $sets;
    }


    /**
     * @param XMLImporter $importer
     * @param $xmlLayouts
     * @param Template $template
     * @return ArrayCollection
     */
    private function readLayouts($importer, $xmlLayouts, $template) {
        $layoutRepository = Shopware()->Models()->getRepository('Shopware\Models\Shop\TemplateConfig\Layout');

        $layouts = new ArrayCollection();
        /* @var Layout $layout */
        foreach ($xmlLayouts->Layout AS $xmlLayout) {
            $file = $importer->changeCursor($xmlLayout, true);
            if (gettype($file) == 'string') {
                $this->printNote('separate xml file loaded: ' . $file);
            }
            $layout = $layoutRepository->findOneBy(array(
                "templateId" =>$template->getId(),
                "name" => $importer->getAttribute('', 'name')
            ));

            if(!$layout){
                $layout = new Layout();
            }

            $layout->setTemplate($template);
            $layout->setName($importer->getAttribute('', 'name'));
            $layout->setType($importer->getAttribute('', 'type'));
            $layout->setTitle($importer->getAttribute('', 'title'));

            if ($importer->getAttribute('', 'parent')) {
                /* @var $l Layout */
                $parentLayout = $layoutRepository->findOneBy(array(
                    "name" => $importer->getAttribute('', 'parent')
                ));
                if (!empty($parentLayout)) {
                    $layout->setParent($parentLayout);
               }
            }

            if ($importer->hasNode('Attributes')) {
                $attributes = $importer->getAssocArray('Attributes');
                $attributes = serialize($attributes);
                $layout->setAttributes($attributes);
            }

            if ($importer->hasNode('Elements')) {
                $elements = $this->readElements($importer, $template);
                $layout->setElements($elements);
            }

            $layouts->add($layout);
            $importer->cursorOut();
        }
        return $layouts;
    }


    /**
     * @param XMLImporter $importer
     * @param Template $template
     * @return ArrayCollection
     */
    private function readElements($importer, $template) {
        $elementRepository = Shopware()->Models()->getRepository('Shopware\Models\Shop\TemplateConfig\Element');
        $layoutRepository = Shopware()->Models()->getRepository('Shopware\Models\Shop\TemplateConfig\Layout');
        $elements = new ArrayCollection();
        /* @var $element Element */
        foreach ($importer->getList('Element') AS $xmlElement) {
            $importer->changeCursor($xmlElement);
            $element = $elementRepository->findOneBy(array(
                "templateId" => $template->getId(),
                "name" => (string) $xmlElement['name']
            ));

            if (!$element){
                $element = new Element();
                $element->setName((string) $xmlElement['name']);
            }

            $element->setTemplate($template);
            $element->setType((string) $xmlElement['type']);
            $element->setPosition((int) $xmlElement['position']);

            $importer->setProp($element, 'DefaultValue');
            if (isset($xmlElement->Selection)) {
                $element->setSelection($importer->getArray('Value', 'Selection'));
            }
            $importer->setProp($element, 'FieldLabel');
            $importer->setProp($element, 'SupportText');
            $importer->setProp($element, 'AllowBlank');
            $importer->setProp($element, 'LessCompatible');
            if (isset($xmlElement->Attributes)) {
                $element->setAttributes($importer->getAssocArray('Attributes'));
            }

            if (isset($xmlElement->Container)) {
                $container = $layoutRepository->findOneBy(array(
                    "templateId" => $template->getId(),
                    "name" => (string) $xmlElement->Container
                ));

                if (!empty($container)) {
                    $element->setContainer($container);
                } else {
                    $this->printNote('layout-container ' . (string) $xmlElement->Container . ' not found for template ' . $template->getName());
                }
            }

            $elements->add($element);
            $importer->cursorOut();
        }
        return $elements;

    }

    /**
     * @param MndConfigImporter $importer
     */
    private function readThemeTemplates($importer) {
        $templateRepository = Shopware()->Models()->getRepository('Shopware\Models\Shop\Template');
        $shopRepository = Shopware()->Models()->getRepository('Shopware\Models\Shop\Shop');

        foreach ($importer->getList('Template') as $xmlTemplate) {
            $importer->changeCursor($xmlTemplate);
            $template = $templateRepository->findOneBy(array(
                'template' => $importer->getAttribute('', 'template')
            ));

            if(!$template){
                $template = new Template();
                $template->setTemplate($importer->getAttribute('', 'template'));
            }

            $template->setName($importer->getAttribute('', 'name'));
            $importer->setProp($template, 'Description');
            $importer->setProp($template, 'Author');
            $importer->setProp($template, 'License');
            $importer->setProp($template, 'Esi');
            $importer->setProp($template, 'Style');
            $importer->setProp($template, 'Emotion');
            $importer->setProp($template, 'Version');

            if (isset($xmlTemplate->Parent)) {
                /* @var $parent Template */
                $parent = $templateRepository->findOneBy(array(
                    "template" => (string) $xmlTemplate->Parent
                ));
                $template->setParent($parent);
            }

            if (isset($xmlTemplate->Shops)) {
                $shops = new ArrayCollection();
                foreach($xmlTemplate->Shops->Host AS $host){
                    $shops->add(
                        $shopRepository->findOneBy(array(
                            "host" => $host
                        ))
                    );
                }
                $template->setShops($shops);
            }

            if (isset($xmlTemplate->Layouts)) {
                $layouts = $this->readLayouts($importer, $xmlTemplate->Layouts, $template);
                $template->setLayouts($layouts);
            }

            if (isset($xmlTemplate->Sets)) {
                $configSets = $this->readConfigSets($importer, $xmlTemplate->Sets, $template);
                $template->setConfigSets($configSets);
            }


            if (isset($xmlTemplate->Elements)) {
                $file = $importer->changeCursor($xmlTemplate->Elements, true);
                if (gettype($file) == 'string') {
                    $this->printNote('separate xml file loaded: ' . $file);
                }
                $elements = $this->readElements($importer, $template);
                $importer->cursorOut();
                $template->setElements($elements);
            }

            Shopware()->Models()->persist($template);
            Shopware()->Models()->flush();

            $this->printMessage( "theme template imported: <comment>". $template->getTemplate() . '</comment> (' . $template->getName() . ')');
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
        $files = $finder->files()->in($this->path)->name(self::CONFIG_FILE);

        /** @var \Symfony\Component\HttpFoundation\File\File $file */
        foreach ($files as $file) {
            try {
                $importer = new MndConfigImporter($file->getPathname());
                $this->printInfo("\nimport file " . $file->getPathname());
                $this->readThemeTemplates($importer);
            } catch (WrongRootException $e) {
                // wrong root tag
            } catch (MismatchedVersionException $e) {
                // wrong version of plugin or shopware
                $this->printError('unmatched version! skipped file ' . $file->getPathname());
            }
        }
    }
}