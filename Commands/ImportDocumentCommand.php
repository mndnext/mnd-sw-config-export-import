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

use ShopwarePlugins\MndConfigExportImport\Components\MndConfigImporter;
use ShopwarePlugins\MndConfigExportImport\Components\XMLImporter;
use ShopwarePlugins\MndConfigExportImport\Exception\MismatchedVersionException;
use ShopwarePlugins\MndConfigExportImport\Exception\WrongRootException;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Finder\Finder;

use Shopware\Models\Document\Element;
use Shopware\Models\Document\Document;

/**
 * Class ImportDocumentCommand
 * @package ShopwarePlugins\MndConfigExportImport\Commands
 */
class ImportDocumentCommand extends MndImportCommand
{
    /**
     * {@inheritdoc}
     */
    protected function configure()
    {
        parent::configure();

        $this
            ->setDescription('Import documents from directory ' . $this->getDefaultPath() . ' or custom source via -p');
    }

    /**
     * {@inheritdoc}
     */
    public function getCommandName() {
        return "document";
    }

    /**
     * @param XMLImporter $importer
     * @param Document $document
     */
    public function readElements($importer, $document, $path) {
        $documentElementRepository = Shopware()->Models()->getRepository('Shopware\Models\Document\Element');
        $elementList = [];
        foreach ($importer->getList() as $xmlElement) {
            $element = $documentElementRepository->findOneBy(
                array(
                    "document" => $document,
                    "name" => $xmlElement->getName()
                )
            );
            if (empty($element)) {
                $element = new Element();
                $element->setDocument($document);
                $element->setName($xmlElement->getName());
            }

            $importer->changeCursor($xmlElement);
                $element->setStyle((string) $xmlElement);
                $filename = $path . '/' . $xmlElement->getName() . '.html';
                if (file_exists($filename)) {
                    $element->setValue(file_get_contents($filename));
                } else {
                    $this->printError('can not set value for element "' . $xmlElement->getName() . '"'. "!\n" . 'file "$filename"  not found');
                }
                $importer->setProp($element, 'Value');
            $importer->cursorOut();
            $elementList[] = $element;
        }
        $document->setElements($elementList);
    }


    /**
     * @param XMLImporter $importer
     * @param Document $document
     */
    public function readDocument($importer, $document, $path) {
        $document->setName($importer->getAttribute('', 'name'));
        $document->setTemplate($importer->getAttribute('', 'template'));
        $importer->setProp($document, 'Numbers');
        $document->setRight($importer->getAttribute('Position', 'right'));
        $document->setLeft($importer->getAttribute('Position', 'left'));
        $document->setTop($importer->getAttribute('Position', 'top'));
        $document->setBottom($importer->getAttribute('Position', 'bottom'));
        $importer->setProp($document, 'PageBreak');
        $importer->cursorInto('Elements');
        $this->readElements($importer, $document, $path);
        $importer->cursorOut();

        Shopware()->Models()->persist($document);
        Shopware()->Models()->flush();

        $this->printMessage('document imported: <comment>' . $document->getName() . '</comment>');
    }

    /**
     * @param XMLImporter $importer
     * @param string $path
     */
    public function readDocuments($importer, $path) {
        $documentRepository = Shopware()->Models()->getRepository('Shopware\Models\Document\Document');

        foreach ($importer->getList('Document') as $xmlDocument) {
            if (isset($xmlDocument['name'])) {
                $document = $documentRepository->findBy(
                    array(
                        "name" => $xmlDocument['name']
                    )
                );
                if (empty($document)) {
                    $document = new Document();
                } else {
                    $document = $document[0];
                }
                $importer->changeCursor($xmlDocument);
                $this->readDocument($importer, $document, $path);
                $importer->cursorOut();
            }
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
        $files = $finder->files()->in($this->path)->name('*.xml');

        /** @var  \Symfony\Component\Finder\SplFileInfo $file */
        foreach ($files as $file) {
            try {
                $importer = new MndConfigImporter($file->getPathname());
                $this->printInfo("\nimport file " . $file->getPathname());
                $this->readDocuments($importer, $file->getPath());
            } catch (WrongRootException $e) {
                // wrong root tag
            } catch (MismatchedVersionException $e) {
                // wrong version of plugin or shopware
               $this->printError('unmatched version! skipped file ' . $file->getPathname());
            }
        }
    }
}