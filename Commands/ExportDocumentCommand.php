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

use Shopware\Models\Document\Element;
use ShopwarePlugins\MndConfigExportImport\Components\MndConfigExporter;
use ShopwarePlugins\MndConfigExportImport\Components\XMLExporter;
use Shopware\Models\Document\Document;

use ShopwarePlugins\MndConfigExportImport\Utils\MndConfigIO;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Class ExportFormCommand
 * @package Shopware\Plugin\MndConfigExportImport\Commands
 */
class ExportDocumentCommand extends MndExportCommand
{
    /**
     * {@inheritdoc}
     */
    protected function configure()
    {
        parent::configure();

        $this
            ->setName('mnd:sw'.$this->getCommandName().':export')
            ->setDescription('Export documents to directory ' . $this->getDefaultPath() . ' or custom target via -p');
    }

    /**
     * {@inheritdoc}
     */
    public function getCommandName() {
        return "document";
    }

    /**
     * @param MndConfigIO $io
     * @param XMLExporter $exporter
     * @param Document $document
     */
    private function writeDocument($io, $document) {
        $io->setSubPath($document->getId() . "_" . $document->getName());

        $exporter = new MndConfigExporter();
        $exporter->newElement('Document');
        $exporter->addAttribute('name', $document->getName());
        $exporter->addAttribute('template', $document->getTemplate());
        $exporter->add('Position', '', false, [
            'right' => $document->getRight(),
            'left' => $document->getLeft(),
            'top' => $document->getTop(),
            'bottom' => $document->getBottom()
        ]);
        $exporter->addProp($document, 'Numbers');
        $exporter->addProp($document, 'PageBreak');


        $exporter->startList('Elements');
        /* @var $element Element */
        foreach($document->getElements() as $element) {
            $exporter->newElement($element->getName());
            $exporter->setText($element->getStyle(), null);
            $filename = $element->getName() . '.html';
            $this->write($io, $filename, $element->getValue());
        }
        $exporter->finishList();

        $this->write($io, self::CONFIG_FILE, $exporter->get(), true);
        $io->setSubPath('');
    }


    /**
     * {@inheritdoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        parent::execute($input, $output);
        $all = Shopware()->Models()->getRepository('Shopware\Models\Document\Document')->findAll();
        try {
            $io = new MndConfigIO($this->getDefaultPath(), $input->getOptions());
            $io->setModulePath($this->getCommandName());
            foreach ($all AS $document) {
                $this->writeDocument($io, $document);
            }
        } catch (\Exception $e) {
            $this->printError($e->getMessage());
        }
    }
}