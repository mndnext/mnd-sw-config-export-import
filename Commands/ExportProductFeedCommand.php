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
use ShopwarePlugins\MndConfigExportImport\Components\MndConfigExporter;
use ShopwarePlugins\MndConfigExportImport\Components\XMLExporter;
use \Doctrine\Common\Util\Debug;

use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use ShopwarePlugins\MndConfigExportImport\Utils\MndConfigIO;
use ShopwarePlugins\MndConfigExportImport\SwProductFeed\SwProductFeedManager;
use ShopwarePlugins\MndConfigExportImport\SwProductFeed\SwProductFeedElement;

/**
 * Class ExportConfigCommand
 * @package Shopware\Plugin\MndConfigExportImport\Commands
 */
class ExportProductFeedCommand extends MndExportCommand
{
    /**
     * {@inheritdoc}
     */
    protected function configure()
    {
        parent::configure();

        $this
            ->setName('mnd:sw'.$this->getCommandName().':export')
            ->setDescription('Export Shopware ProductFeeds to directory ' . $this->getDefaultPath() . ' or custom target via -p');
    }

    /**
     * {@inheritdoc}
     */
    public function getCommandName() {
        return "productfeed";
    }

    /**
     * @param MndConfigIO $io
     * @param ProductFeed $feed
     */
    private function writeProductfeed($io, $feed)
    {
        $exporter = new MndConfigExporter();
        $io->setSubPath($feed->getName());

        $exporter->newElement('Productfeed');
        $exporter->addAttribute('id', $feed->getId());
        $exporter->addAttribute('name', $feed->getName());
        $exporter->addAttribute('active', (int)$feed->getActive());
        $exporter->addAttribute('show', (int)$feed->getShow());
        //$dbElement->setLastExport($element->getLastExport()); // inherit from existing

        $exporter->addProp($feed, 'Hash');
        $exporter->addProp($feed, 'CountArticles');
        $exporter->addProp($feed, 'Interval');
        $exporter->addProp($feed, 'FormatId');
        $exporter->addProp($feed, 'Filename');
        $exporter->addProp($feed, 'EncodingId');
        $exporter->addProp($feed, 'CategoryId');
        $exporter->addProp($feed, 'CurrencyId');
        $exporter->addProp($feed, 'CustomerGroupId');
        $exporter->addProp($feed, 'PartnerId');
        $exporter->addProp($feed, 'LanguageId');

        $exporter->addProp($feed, 'ActiveFilter');
        $exporter->addProp($feed, 'ImageFilter');
        $exporter->addProp($feed, 'StockMinFilter');
        $exporter->addProp($feed, 'InstockFilter');
        $exporter->addProp($feed, 'PriceFilter');
        $exporter->addProp($feed, 'CountFilter');

        $exporter->add('OwnFilter', '', false, ['file' => 'filter.sql']);
        $this->write($io, 'filter.sql', $feed->getOwnFilter());
        //$exporter->addProp($feed, 'OwnFilter');

        $exporter->add('Header', '', false, ['file' => 'header.html']);
        $this->write($io,'header.html', $feed->getHeader());
        //$exporter->addProp($feed, 'Header', true);
        $exporter->add('Body', '', false, ['file' => 'body.html']);
        $this->write($io, 'body.html', $feed->getBody());
        //$exporter->addProp($feed, 'Body', true);
        $exporter->add('Footer', '', false, ['file' => 'footer.html']);
        $this->write($io, 'footer.html', $feed->getFooter());
        //$exporter->addProp($feed, 'Footer', true);
        $exporter->addProp($feed, 'ShopId');
        //$dbElement->setCacheRefreshed($element->getCacheRefreshed()); // inherit from existing
        $exporter->addProp($feed, 'VariantExport');
        // always set dirty on import, something probably changed.
        //$exporter->addProp($feed, 'Dirty');
        $exporter->add('Dirty', 1);

        if (null != $feed->getAttribute()) {
            $attributes = get_object_vars(Debug::export($feed->getAttribute(), 1));
            $exporter->startList('Attributes');
            foreach ($attributes as $key => $value) {
                $exporter->newElement('Attribute');
                $exporter->addAttribute('key', $key);
                $exporter->setText($value);
            }
            $exporter->finishList();
        }

        $this->write($io, self::CONFIG_FILE, $exporter->get(), true);
        $io->setSubPath('');
    }

    /**
     * {@inheritdoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        parent::execute($input, $output);

        try {
            $io = new MndConfigIO($this->getDefaultPath(), $input->getOptions());
            $io->setModulePath($this->getCommandName());
            $exportsRepository = Shopware()->Models()->getRepository('Shopware\Models\ProductFeed\ProductFeed');
            foreach ($exportsRepository->findAll() as $feed) {
                $this->writeProductfeed($io, $feed);
            }
        } catch (\Exception $e) {
            $this->printError($e->getMessage());
        }

    }
}