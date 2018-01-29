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

use Shopware\Models\Site\Group;
use Shopware\Models\Site\Site;
use ShopwarePlugins\MndConfigExportImport\Components\MndConfigExporter;
use ShopwarePlugins\MndConfigExportImport\Components\XMLExporter;
use \Doctrine\Common\Util\Debug;

use ShopwarePlugins\MndConfigExportImport\Utils\MndConfigIO;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Class ExportCmsCommand
 * @package Shopware\Plugin\MndConfigExportImport\Commands
 */
class ExportCmsCommand extends MndExportCommand
{
    /**
     * {@inheritdoc}
     */
    protected function configure()
    {
        parent::configure();

        $this
            ->setName('mnd:sw'.$this->getCommandName().':export')
            ->setDescription('Export Cms pages to directory ' . $this->getDefaultPath() . ' or custom target via -p');
    }

    /**
     * {@inheritdoc}
     */
    public function getCommandName() {
        return "cms";
    }

    /**
     * @param XMLExporter $exporter
     * @param Group $siteGroup
     */
    private function writeSiteGroup($exporter, $siteGroup) {
        $exporter->newElement('CmsGroup');
        $exporter->addAttribute('key', $siteGroup->getKey());
        $exporter->addAttribute('name', $siteGroup->getName());
        $exporter->addAttribute('active', (int) $siteGroup->getActive());
        if ($siteGroup->getMapping()) {
            $exporter->add('Mapping', '', false, ['key' => $siteGroup->getMapping()->getKey()]);
        }
    }

    /**
     * @param MndConfigIO $io
     * @param XMLExporter $exporter
     * @param Site $site
     */
    private function writeSite($io, $exporter, $site) {
        $htmlFile =  preg_replace('/[^a-zA-Z0-9]/', '', $site->getDescription());
        $htmlFile = $site->getId() . '_' . $htmlFile . '.html';
        $this->write($io, $htmlFile, $site->getHtml());

        $exporter->newElement('CmsPage');
        $exporter->addAttribute('id', $site->getId());
        $exporter->addAttribute('html', $htmlFile);
        $exporter->addProp($site, 'Tpl1variable');
        $exporter->addProp($site, 'Tpl1path');
        $exporter->addProp($site, 'Tpl2variable');
        $exporter->addProp($site, 'Tpl2path');
        $exporter->addProp($site, 'Tpl3variable');
        $exporter->addProp($site, 'Tpl3path');
        $exporter->addProp($site, 'Description');
        $exporter->addProp($site, 'PageTitle');
        $exporter->addProp($site, 'MetaKeywords');
        $exporter->addProp($site, 'MetaDescription');
        //$exporter->addProp($site, 'Html', true);
        $exporter->addProp($site, 'Grouping');
        $exporter->addProp($site, 'Position');
        $exporter->addProp($site, 'Link');
        $exporter->addProp($site, 'Target');
        $exporter->addProp($site, 'ShopIds');

        if ($site->getParent() && $site->getParentId() != 0){
            $exporter->add('Parent', $site->getParent()->getDescription(), false, ['position' => $site->getParent()->getPosition()]);
        }

        if (null != $site->getAttribute()) {
            $attributes = get_object_vars(Debug::export($site->getAttribute(), 1));
            $exporter->startList('Attributes');
            foreach ($attributes as $key => $value) {
                $exporter->newElement('Attribute');
                $exporter->addAttribute('key', $key);
                $exporter->setText($value);
            }
            $exporter->finishList();
        }
    }


    /**
     * {@inheritdoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        parent::execute($input, $output);

        $exporter = new MndConfigExporter();
        try {
            $io = new MndConfigIO($this->getDefaultPath(), $input->getOptions());
            $io->setModulePath($this->getCommandName());
            $cmsRepository = Shopware()->Models()->getRepository('Shopware\Models\Site\Site');
            $io->setSubPath('templates');
            $exporter->startList('CmsPages');
            foreach ($cmsRepository->findAll() as $site) {
                $this->writeSite($io, $exporter, $site);
            }
            $exporter->finishList();
            $io->setSubPath('');  // remove subpath

            $cmsGroupRepository = Shopware()->Models()->getRepository('Shopware\Models\Site\Group');
            $exporter->startList('CmsGroups');
            foreach ($cmsGroupRepository->findAll() as $siteGroup) {
                $this->writeSiteGroup($exporter, $siteGroup);
            }
            $exporter->finishList();

            $this->write($io, self::CONFIG_FILE, $exporter->get(), true);

        } catch (\Exception $e) {
            $this->printError($e->getMessage());
        }


    }
}