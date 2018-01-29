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

use Shopware\Models\Shop\Shop;
use Shopware\Models\Shop\Template;
use Shopware\Models\Shop\TemplateConfig\Element;
use Shopware\Models\Shop\TemplateConfig\Layout;
use Shopware\Models\Shop\TemplateConfig\Set;
use ShopwarePlugins\MndConfigExportImport\Components\MndConfigExporter;
use ShopwarePlugins\MndConfigExportImport\Components\XMLExporter;
use ShopwarePlugins\MndConfigExportImport\SwThemeConfig\SwThemeConfigManager;
use ShopwarePlugins\MndConfigExportImport\Utils\MndConfigIO;

use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Class ExportConfigCommand
 * @package Shopware\Plugin\MndConfigExportImport\Commands
 */
class ExportThemeConfigCommand extends MndExportCommand
{
    const NOT_SEPARATE = 0;
    const SEPARATE_FILE = 1;
    const SEPARATE_PER_ELEMENT = 2;


    /** @var SwThemeConfigManager $manager */
    private $manager;

    /**
     * {@inheritdoc}
     */
    protected function configure()
    {
        parent::configure();

        $this
            ->setName('mnd:sw' . $this->getCommandName() . ':export')
            ->setDescription('Export Theme Config to directory ' . $this->getDefaultPath() . ' or custom target via -p');
    }

    /**
     * {@inheritdoc}
     */
    public function getCommandName()
    {
        return "themeconfig";
    }

    function getErrors()
    {
        return $this->manager->getErrors();
    }


    /**
     * Generates a node with children that can be in separate files. Possible is to have the children
     * - in the same xml-tree
     * - in one seperate file starting with a root-node $listName
     *   and the parent xml-tree will have a node $listName with attribute "file" and the filepath as value
     * - in the seperate files for every child, root-node is $elementName
     *   the parent xml-tree will have the node $listName and for every element a childnode with name $elementName
     *   that has an attribute "file" with filepath as value.
     *
     * @param MndConfigIO $io
     * @param XMLExporter $exporter
     * @param mixed $elements
     * @param int $separate
     * @param string $listName
     * @param string $elementName
     */
    private function writeSeparateElements($io, $exporter, $elements, $separate, $listName, $elementName) {
        $exporterMain = $exporter;
        $exporterMain->startList($listName);

        switch ($separate) {
            case self::SEPARATE_PER_ELEMENT:
                //$exporterMain = $exporter;
                break;
            case self::SEPARATE_FILE:
                //$exporterMain = $exporter;
                $exporter = new XMLExporter($listName);
                break;
            case self::NOT_SEPARATE:
            default:
                $separate = self::NOT_SEPARATE;
        }

        $func = 'write' . $elementName;
        foreach ($elements as $element) {
            if ($separate == self::SEPARATE_PER_ELEMENT) {
                $exporter = new XMLExporter($elementName);
            } else {
                $exporter->newElement($elementName);
            }
            $this->$func($io, $exporter, $element);

            // write every element into single file
            if ($separate == self::SEPARATE_PER_ELEMENT) {
                $filename = $element->getName() . '.xml';
                $this->write($io, $filename, $exporter->get());
                $exporterMain->newElement($elementName);
                $exporterMain->addAttribute('file', $listName . '/' . $filename);
            }
        }

        if ($separate == self::SEPARATE_FILE) {
            $filename = $listName . '.' . self::CONFIG_FILE;
            $this->write($io, $filename, $exporter->get(), true);
            $exporterMain->addAttribute('file', $filename);
        }
        $exporterMain->finishList();
    }

    /**
     * @param XMLExporter $exporter
     * @param Set $set
     */
    private function writeSet($io, $exporter, $set) {
        $exporter->addAttribute('name', $set->getName());
        $exporter->addAttribute('description', $set->getDescription());
        $exporter->addArray('Value', $set->getValues());
    }


    /**
     * @param MndConfigIO $io
     * @param XMLExporter $exporter
     * @param Set[] $sets
     * @param int $separate if true will write every element into a separate file
     */
    private function writeConfigSets($io, $exporter, $sets, $separate = self::NOT_SEPARATE) {
        $this->writeSeparateElements($io, $exporter, $sets, $separate, 'Sets', 'Set');
    }

    /**
     * @param XMLExporter $exporter
     * @param Layout $layout
     */
    private function writeLayout($io, $exporter, $layout)
    {
        $exporter->addAttribute('name', $layout->getName());
        $exporter->addAttribute('type', $layout->getType());
        $exporter->addAttribute('title', $layout->getTitle());
        if ($layout->getParent()) {
            $exporter->addAttribute('parent', $layout->getParent()->getName());
        }
        if ($layout->getAttributes()) {
            $array = (array)$layout->getAttributes();
            if (!empty($array)) {
                $exporter->startList('Attributes');
                $exporter->addAssocArray($array);
                $exporter->finishList();
            }
        }
        if ($layout->getElements()->count()) {
            $this->writeElements($io, $exporter, $layout->getElements());
        }
    }

    /**
     * @param MndConfigIO $io
     * @param XMLExporter $exporter
     * @param Layout[] $layouts
     * @param bool $separate if true will write every element into a separate file
     */
    private function writeLayouts($io, $exporter, $layouts, $separate = self::NOT_SEPARATE) {
        $this->writeSeparateElements($io, $exporter, $layouts, $separate, 'Layouts', 'Layout');
    }

    /**
     * @param XMLExporter $exporter
     * @param Element $element
     */
    private function writeElement($io, $exporter, $element)
    {
        $exporter->addAttribute('name', $element->getName());
        $exporter->addAttribute('type', $element->getType());
        $exporter->addAttribute('position', $element->getPosition());

        $exporter->addProp($element, 'DefaultValue');
        if ($element->getSelection()) {
            $exporter->startList('Selection');
            $exporter->addArray('Value', $element->getSelection());
            $exporter->finishList();
        }
        $exporter->addProp($element, 'FieldLabel');
        $exporter->addProp($element, 'SupportText');
        $exporter->addProp($element, 'AllowBlank');
        $exporter->add('LessCompatible', (int)$element->isLessCompatible());
        $container = $element->getContainer();
        if (!empty($container)) {
            $exporter->add('Container', $container->getName());
        }
    }

    /**
     * @param MndConfigIO $io
     * @param XMLExporter $exporter
     * @param Element[] $elements
     * @param int $separate if true will write every element into a separate file
     */
    private function writeElements($io, $exporter, $elements, $separate = self::NOT_SEPARATE) {
        $this->writeSeparateElements($io, $exporter, $elements, $separate, 'Elements', 'Element');
    }

    /**
     * @param MndConfigIO $io
     * @param Template $template
     */
    private function writeTemplate($io, $template) {
        $exporter = new MndConfigExporter();

        $exporter->newElement('Template');
        $exporter->addAttribute('template', $template->getTemplate());
        $exporter->addAttribute('name', $template->getName());

        $exporter->addProp($template, 'Description');
        $exporter->addProp($template, 'Author');
        $exporter->addProp($template, 'License');
        $exporter->addProp($template, 'Esi');
        $exporter->addProp($template, 'Style');
        $exporter->add('Emotion', $template->getEmotion(true)); // paramter has no meaning?
        $exporter->addProp($template, 'Version');
        if ($template->getParent()) {
            $exporter->add('Parent', $template->getParent()->getTemplate());
        }
        if ($template->getShops()->count()) {
            $exporter->startList('Shops');
            /* @var Shop $shop */
            foreach ($template->getShops() AS $shop) {
                $exporter->add('Host', $shop->getHost());
            }
            $exporter->finishList();
        }

        if ($template->getElements()->count()){
            $io->setSubPath($template->getTemplate());
            $this->writeElements($io, $exporter, $template->getElements(), self::SEPARATE_FILE);
        }

        if ($template->getLayouts()->count()) {
            $io->setSubPath($template->getTemplate() . '/Layouts');
            $this->writeLayouts($io, $exporter, $template->getLayouts(), self::SEPARATE_PER_ELEMENT);
        }

        if ($template->getConfigSets()) {
            $io->setSubPath($template->getTemplate() . '/Sets');
            $this->writeConfigSets($io, $exporter, $template->getConfigSets(), self::SEPARATE_PER_ELEMENT);
        }

        $io->setSubPath($template->getTemplate());
        $this->write($io, self::CONFIG_FILE, $exporter->get(), true);
    }


    /**
     * {@inheritdoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        parent::execute($input, $output);

        $templateRepository = Shopware()->Models()->getRepository('Shopware\Models\Shop\Template');
        try {
            $io = new MndConfigIO($this->getDefaultPath(), $input->getOptions());
            $io->setModulePath($this->getCommandName());
            foreach ($templateRepository->findAll() as $template) {
                $this->writeTemplate($io, $template);
            }
        } catch (\Exception $e) {
            $this->printError($e->getMessage());
        }
    }
}