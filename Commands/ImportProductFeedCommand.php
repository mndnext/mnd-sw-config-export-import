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

use Shopware\Models\ProductFeed\ProductFeed;
use ShopwarePlugins\MndConfigExportImport\Components\MndConfigImporter;
use ShopwarePlugins\MndConfigExportImport\Components\XMLImporter;
use ShopwarePlugins\MndConfigExportImport\Exception\MismatchedVersionException;
use ShopwarePlugins\MndConfigExportImport\Exception\WrongRootException;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Finder\Finder;

/**
 * Class ImportMailCommand
 * @package Shopware\Plugin\MndConfigExportImport\Commands
 */
class ImportProductFeedCommand extends MndImportCommand
{
    /**
     * {@inheritdoc}
     */
    protected function configure() {
        parent::configure();

        $this
            ->setDescription('Import ProductFeeds from directory ' . $this->getDefaultPath() . ' or custom source via -p');
    }

    /**
     * {@inheritdoc}
     */
    public function getCommandName() {
        return "productfeed";
    }

    /**
     * @param XMLImporter $importer
     * @param ProductFeed $feed
     */
    private function readFeedAttributes($importer, $feed) {
        if ($importer->cursorInto('Attributes')) {
            $attributes = [];
            foreach ($importer->getList('Attribute') as $xmlAttribute) {
                $attributes[$xmlAttribute['key']] = $xmlAttribute;
            }
            if ($attributes) {
                $feed->setAttribute($attributes);
            }
            $importer->cursorOut();
        }
    }

    /**
     * @param XMLImporter $importer
     * @param ProductFeed $feed
     */
    private function readProductfeed($importer, $feed) {
        $feed->setName($importer->getAttribute('', 'name'));
        $feed->setActive($importer->getAttribute('', 'active'));
        $feed->setShow($importer->getAttribute('', 'show'));
        $importer->setProp($feed, 'Hash');
        $importer->setProp($feed, 'CountArticles');
        $importer->setProp($feed, 'Interval');
        $importer->setProp($feed, 'FormatId');
        $importer->setProp($feed, 'Filename');
        $importer->setProp($feed, 'EncodingId');
        $importer->setProp($feed, 'CategoryId');
        $importer->setProp($feed, 'CurrencyId');
        $importer->setProp($feed, 'CustomerGroupId');
        $importer->setProp($feed, 'PartnerId');
        $importer->setProp($feed, 'LanguageId');
        $importer->setProp($feed, 'ActiveFilter');
        $importer->setProp($feed, 'ImageFilter');
        $importer->setProp($feed, 'StockMinFilter');
        $importer->setProp($feed, 'InstockFilter');
        $importer->setProp($feed, 'PriceFilter');
        $importer->setProp($feed, 'CountFilter');
        //$importer->setProp($feed, 'OwnFilter');
        $feed->setOwnFilter($importer->readFile('OwnFilter'));
        //$importer->setProp($feed, 'Header');
        $feed->setHeader($importer->readFile('Header'));
        //$importer->setProp($feed, 'Body');
        $feed->setBody($importer->readFile('Body'));
        //$importer->setProp($feed, 'Footer');
        $feed->setFooter($importer->readFile('Footer'));
        $importer->setProp($feed, 'ShopId');
        //$dbElement->setCacheRefreshed($element->getCacheRefreshed()); // inherit from existing
        $importer->setProp($feed, 'VariantExport');
        $importer->setProp($feed, 'Dirty');

        $feed->setExpiry(new \DateTime('now')); // always expire when imported
        $feed->setLastChange(null);                  // imported are new and so never changed before
        //d.m.Y h:i:s

        $this->readFeedAttributes($importer, $feed);
    }

    /**
     * @param MndConfigImporter $importer
     */
    private function readProductfeeds($importer) {
        $exportsRepository = Shopware()->Models()->getRepository('Shopware\Models\ProductFeed\ProductFeed');

        foreach ($importer->getList('Productfeed') as $xmlFeed) {
            $importer->changeCursor($xmlFeed);
            
            $feed = $exportsRepository->findOneBy(array(
                'id' => $xmlFeed['id']
            ));

            $new = false;
            if (empty($feed)) {
                $feed = new \Shopware\Models\ProductFeed\ProductFeed();
                $new = true;
            }

            $this->readProductfeed($importer, $feed);
            
            Shopware()->Models()->persist($feed);
            Shopware()->Models()->flush();

            if ($new) {
                Shopware()->Db()->executeQuery("UPDATE s_export SET id = " . $xmlFeed['id'] . " WHERE Id = " . $feed->getId());
            }
                
            $this->printMessage( "productfeed imported: <comment>" . $feed->getName() . "</comment>");


            $importer->cursorOut();
        }
        
    }
    
    
    /**
     * {@inheritdoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output) {
        parent::execute($input, $output);

        $finder = new Finder();

        /** @var Finder[] $files */
        $files = $finder->files()->in($this->path)->name('*.xml');

        /** @var \Symfony\Component\HttpFoundation\File\File $file */
        foreach ($files as $file) {
            try {
                $importer = new MndConfigImporter($file->getPathname());
                $this->printInfo("\nimport file " . $file->getPathname());
                $this->readProductfeeds($importer);
            } catch (WrongRootException $e) {
                // wrong root tag
            } catch (MismatchedVersionException $e) {
                // wrong version of plugin or shopware
                $this->printError('unmatched version! skipped file ' . $file->getPathname());
            }
        }
    }
}