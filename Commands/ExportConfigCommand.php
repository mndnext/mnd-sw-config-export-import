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

use Shopware\Commands\ShopwareCommand;
use ShopwarePlugins\MndConfigExportImport\Components\MndConfigExporter;
use ShopwarePlugins\MndConfigExportImport\Components\XMLExporter;
use ShopwarePlugins\MndConfigExportImport\Utils\MndConfigIO;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Input\InputOption;

/**
 * Class ExportConfigCommand
 * @package Shopware\Plugin\MndConfigExportImport\Commands
 */
class ExportConfigCommand extends MndExportCommand
{
    const SHOPWARE_GENERAL = '-ShopwareGeneral-';

    private $fallbackShopId = 1;

    /**
     * {@inheritdoc}
     */
    protected function configure()
    {
        parent::configure();

        $this
            ->setName('mnd:sw'.$this->getCommandName().':export')
            ->setDescription('Export Shopware Config to directory ' . $this->getDefaultPath() . ' or custom target via -p');

        $this->addOption(
            'shop',
            null,
            InputOption::VALUE_REQUIRED,
            'Export a single shop. A shop id is expected -- default: all shops.');
    }

    /**
     * {@inheritdoc}
     */
    public function getCommandName() {
        return "config";
    }

    /**
     * @param MndConfigIO $io
     * @param XMLExporter $exporter
     * @param int $shopId
     * @param int $parentId
     */
    private function writeShopConfig($io, $shopId, $parentId, $shopName) {
        if ($shopName === '') {
            $shopName = 'Unbenannt';
        }
        $filename = $shopId . '_' . preg_replace('/[^a-zA-Z0-9]/', '', $shopName) . '.xml';
        $exporter = new MndConfigExporter();

        $formConfig = $this->getFormConfig($shopId, $parentId);
        foreach ($formConfig as $name => $form) {
            if (!$name) {
                // special treatment for Shopware hidden configuration
                $name = self::SHOPWARE_GENERAL;
            }
            $exporter->startList('Config');
            $exporter->addAttribute('shop', $shopId);
            $exporter->addAttribute('name', $name);

            if (count($form) > 0 && $form[0]['pluginName'])
            if ($form[0]['pluginName']) {
                $exporter->addAttribute('plugin', $form[0]['pluginName']);
            }

            foreach ($form as $element) {
                //if (!$this->noComments) {
                    if ($element['label']) {
                        $exporter->addComment($element['label']);
                    }
                    if ($element['description']) {
                        $exporter->addComment($element['description']);
                    }
                //}
                $exporter->newElement('Value');
                $exporter->addAttribute('name', $element['name']);
                if (
                    $shopId != $this->fallbackShopId
                    && $element['currentShopval'] === null
                ) {
                    $this->printMessage( $shopId . ":" . $element['name'] . " is inherited.\n");
                    $inherited = true;
                } else {
                    $inherited = false;
                }
                //@todo for easier use, values can be unserialized, BUT beware to not break the xml-file
                //@todo "values" is user-input and can be anything! (for example arrays and html-strings are possible)
                /*
                if ($element['value']) {
                    $value = unserialize($element['value']);
                } else {
                    $value = $element['value'];
                }
                if (is_numeric($value) || !$value) {
                    $cdata = false;
                } else {
                    $cdata = true;
                }
                var_dump($value);
                */
                if (!$inherited) {
                    $value = $element['value'];
                    $cdata = true;
                    $exporter->setText($value, $cdata);
                } else {
                    $exporter->addAttribute('inherited', 'true');

                }
                $exporter->finishElement();
            }
            $exporter->finishList();
        }
        $this->write($io, $filename, $exporter->get(), true);
    }

    protected function getFormConfig($shopId, $parentId) {
        $sql = "
            SELECT
              e.name as name,
              COALESCE(currentShop.value, parentShop.value, fallbackShop.value, e.value) as value,
              forms.name as form,
              currentShop.value as currentShopval,
              parentShop.value as parentShopval,
              fallbackShop.value as fallbackShopval,
              plugin.name as pluginName,
              e.label as label,
              e.description as description,
              e.position as elementPosition,
              forms.label as formlabel,
              forms.id as formid

            FROM s_core_config_elements e

            LEFT JOIN s_core_config_values currentShop
              ON currentShop.element_id = e.id
              AND currentShop.shop_id = :currentShopId

            LEFT JOIN s_core_config_values parentShop
              ON parentShop.element_id = e.id
              AND parentShop.shop_id = :parentShopId

            LEFT JOIN s_core_config_values fallbackShop
              ON fallbackShop.element_id = e.id
              AND fallbackShop.shop_id = :fallbackShopId

            LEFT JOIN s_core_config_forms forms
              ON forms.id = e.form_id

            LEFT JOIN s_core_plugins plugin
              ON plugin.id = forms.plugin_id

            ORDER BY forms.name ASC, e.position ASC
              ";
        $list = Shopware()->Db()->fetchAll($sql, array(
            "currentShopId" => $shopId,
            "parentShopId" => $parentId,
            "fallbackShopId" => $this->fallbackShopId
        ));
        $forms = [];
        foreach ($list as $element) {
            $forms[$element['form']][] = $element;
        }
        return $forms;
    }

    private function getShopsFromDb()
    {
        $sql = "SELECT id,main_id,name FROM s_core_shops";
        return Shopware()->Db()->fetchAll($sql);

    }

    /**
     * {@inheritdoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        parent::execute($input, $output);
        $selectedShopId = $input->getOption('shop');
        if(!empty($selectedShopId)){
            $selectedShopId = explode(',', $selectedShopId);
        } else {
            $selectedShopId = null;
        }

        $shopList = $this->getShopsFromDb();
        if(empty($shopList)){
            throw new \Exception("No shop found");
        }

        try {
            $io = new MndConfigIO($this->getDefaultPath(), $input->getOptions());
            $io->setModulePath($this->getCommandName());
            foreach ($shopList as $shop) {
                if (!$selectedShopId || in_array($shop['id'], $selectedShopId)) {
                    $this->writeShopConfig($io, $shop['id'], $shop['main_id'], $shop['name']);
                }
            }
        } catch (\Exception $e) {
            $this->printError($e->getMessage());
        }
    }


}