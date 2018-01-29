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
use ShopwarePlugins\MndConfigExportImport\Components\MndConfigImporter;
use ShopwarePlugins\MndConfigExportImport\Components\XMLImporter;

use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Finder\Finder;

/**
 * Class ImportConfigCommand
 * @package ShopwarePlugins\MndConfigExportImport\Commands
 */
class ImportCmsCommand extends MndImportCommand
{

    /**
     * {@inheritdoc}
     */
    protected function configure() {
        parent::configure();

        $this
            ->setDescription('Import Sites from directory ' . $this->getDefaultPath() . ' or custom source via -p');
    }

    /**
     * {@inheritdoc}
     */
    public function getCommandName() {
        return "cms";
    }

    /**
     * @param XMLImporter $importer
     * @param Site $cmsPage
     */
    private function readCmsPageAttributes($importer, $cmsPage) {
        if ($importer->cursorInto('Attributes')) {
            $attributes = [];
            foreach ($importer->getList('Attribute') as $xmlAttribute) {
                $attributes[$xmlAttribute['key']] = $xmlAttribute;
            }
            if ($attributes) {
                $cmsPage->setAttribute($attributes);
            }
            $importer->cursorOut();
        }
    }

    /**
     * @param XMLImporter $importer
     */
    private function readCmsPages($importer) {
        $cmsRepository = Shopware()->Models()->getRepository('Shopware\Models\Site\Site');
        $inCmsPages = $importer->cursorInto('CmsPages');
        $xmlList = $importer->getList('CmsPage');
        foreach ($xmlList as $xmlPage) {
            $importer->changeCursor($xmlPage);
            $cmsPage = $cmsRepository->findOneBy(
                array(
                    "id" => $xmlPage['id']
                )
            );

            if(empty($cmsPage)){
                $cmsPage = new Site();
            }

            $importer->setProp($cmsPage, 'Tpl1variable');
            $importer->setProp($cmsPage, 'Tpl1path');
            $importer->setProp($cmsPage, 'Tpl2variable');
            $importer->setProp($cmsPage, 'Tpl2path');
            $importer->setProp($cmsPage, 'Tpl3variable');
            $importer->setProp($cmsPage, 'Tpl3path');
            $importer->setProp($cmsPage, 'Description');
            $importer->setProp($cmsPage, 'PageTitle');
            $importer->setProp($cmsPage, 'MetaKeywords');
            $importer->setProp($cmsPage, 'MetaDescription');
            //$importer->setProp($cmsPage, 'Html');
            $html = $importer->readFile('', 'html', 'templates');
            if ($html) {
                $cmsPage->setHtml($html);
            }
            $importer->setProp($cmsPage, 'Grouping');
            $importer->setProp($cmsPage, 'Position');
            $importer->setProp($cmsPage, 'Link');
            $importer->setProp($cmsPage, 'Target');
            $importer->setProp($cmsPage, 'ShopIds');

            if ($xmlPage->Parent){
                $parent = $cmsRepository->findOneBy(array(
                    "description" => $xmlPage->Parent,
                    "position" => $xmlPage->Parent['position']
                ));
                if ($parent){
                    $cmsPage->setParent($parent);
                }
            }
            $this->readCmsPageAttributes($importer, $cmsPage);

            Shopware()->Models()->persist($cmsPage);
            Shopware()->Models()->flush();
            $this->printMessage( "cms page imported: ".$cmsPage->getPosition()." - <comment>".$cmsPage->getDescription() . '</comment>');
            $importer->cursorOut();
        }
        if (empty($xmlList)) {
            $this->printComment('no cms page to import');
        }
        if ($inCmsPages) {
            $importer->cursorOut();
        }
    }

    /**
     * @param XMLImporter $importer
     */
    private function readCmsGroups($importer) {
        $cmsGroupRepository = Shopware()->Models()->getRepository('Shopware\Models\Site\Group');
        $inCmsGroups = $importer->cursorInto('CmsGroups');
        $xmlList = $importer->getList('CmsGroup');
        foreach ($xmlList as $xmlGroup) {
            $importer->changeCursor($xmlGroup);
            $cmsGroup = $cmsGroupRepository->findOneBy(
                array(
                    "key" => $xmlGroup['key']
                )
            );

            if(empty($cmsGroup)){
                $cmsGroup = new Group();
            }

            $cmsGroup->setName($xmlGroup['name']);
            $cmsGroup->setActive((bool) (string) $xmlGroup['active']);

            if ($importer->getList('Mapping')) {
                $mapping = [];
                foreach ($importer->getList('Mapping') as $xmlMapping) {
                    $cmsGroupM = $cmsGroupRepository->findOneBy(
                        array(
                            "key" => (string) $xmlMapping
                        )
                    );
                    if ($cmsGroupM) {
                        $mapping[] = $cmsGroupM;
                    }
                }
                if (!empty($mappign)) {
                    $cmsGroup->setMapping($mapping);
                }
            }

            Shopware()->Models()->persist($cmsGroup);
            Shopware()->Models()->flush();
            $this->printMessage("cms group imported: <comment>".$cmsGroup->getKey() . '</comment>');
            $importer->cursorOut();
        }
        if (empty($xmlList)) {
            $this->printComment('no cms group to import');
        }
        if ($inCmsGroups) {
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
        $files = $finder->files()->in($this->path)->name(self::CONFIG_FILE);

        /** @var  \Symfony\Component\Finder\SplFileInfo $file */
        foreach ($files as $file) {
            $this->printInfo("\nimport file " . $file->getPathname());
            $importer = new MndConfigImporter($file->getPathname());
            $this->readCmsPages($importer);
            $this->readCmsGroups($importer);
        }
    }
}