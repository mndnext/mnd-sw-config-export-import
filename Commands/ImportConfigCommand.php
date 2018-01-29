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

/**
 * Class ImportConfigCommand
 * @package ShopwarePlugins\MndConfigExportImport\Commands
 */
class ImportConfigCommand extends MndImportCommand
{
    const SHOPWARE_GENERAL = '-ShopwareGeneral-';

    /**
     * {@inheritdoc}
     */
    protected function configure()
    {
        parent::configure();

        $this
            ->setDescription('Import Shopware Config from directory ' . $this->getDefaultPath() . ' or custom source via -p')
            ->addOption(
                'shop',
                null,
                InputOption::VALUE_REQUIRED,
                'Set configuration for shop id -- default: all shops.'
            );
    }

    /**
     * {@inheritdoc}
     */
    public function getCommandName()
    {
        return "config";
    }

    private function updateExistingElement($shopId, $dbElement, $importValue, $formName, $elementName, $isInherited) {
        $name = "<comment>$formName:$elementName</comment>";
        // so we have a valid element to work on
        $newValueIsDefaultValue = false;
        if ((String) $dbElement['defaultValue'] === $importValue) {
            $newValueIsDefaultValue = true;
        }

        // values entry exists
        $configValuesEntryExists = true;
        if ($dbElement['configValuesId'] === null) {
            $configValuesEntryExists = false;
        }

        // new value is default value and no config Value Entry exists
        if(
            $newValueIsDefaultValue
            && !$configValuesEntryExists
        ){
            // nothing to do
            $this->printMessage("Value of Element $name is identical to default and will be inherited. Doing nothing.");
            return;
        }

        // new value is default value or value is marked as inherited
        if (
            ($configValuesEntryExists && $newValueIsDefaultValue)
            || $isInherited
        ) {

            $this->printImportant("Deleting Value of Element $name to reset to default value. Doing nothing.");
            $sql = "DELETE FROM s_core_config_values WHERE id = :configValuesId";
            Shopware()->Db()->executeQuery($sql, array(
                "configValuesId" => $dbElement['configValuesId']
            ));
            return;
        }

        // new value is identical to existing value.
        if (
            $configValuesEntryExists
            && $dbElement['configValue'] === $importValue
        ) {

            $this->printMessage("Value of Element $name is identical to config_values entry. Doing nothing.");
            return;

        }

        // new value is different from default value
        if ($configValuesEntryExists) {
            // update
            $sql = "UPDATE s_core_config_values SET `value` = :newValue WHERE id = :configValueId";
            Shopware()->Db()->executeQuery($sql, array(
                "newValue" => $importValue,
                "configValueId" => $dbElement['configValuesId']
            ));
            $this->printMessage("Value of Element $name <info>updated</info>");
        } else {
            // insert
            $sql = "INSERT INTO s_core_config_values (element_id,shop_id,`value`) VALUES (:elementId,:shopId,:elementValue)";
            Shopware()->Db()->executeQuery($sql, array(
                "elementId" => $dbElement['elementId'],
                "shopId" => $shopId,
                "elementValue" => $importValue
            ));
            $this->printInfo("Value of Element $name <info>inserted</info>");
        }
    }



    private function getExistingElement($shopId, $formName, $elementName)
    {
        // check if element and according form exists at all
        // we do not create new config forms

        $sql = "
              SELECT
                  e.id as elementId,
                  e.value as defaultValue,
                  v.value as configValue,
                  v.id as configValuesId,
                  v.shop_id as shopId
              FROM
                  s_core_config_elements e
              LEFT JOIN
                  s_core_config_forms f
              ON
                  e.form_id = f.id

              LEFT JOIN
                  s_core_config_values v
              ON
                  e.id = v.element_id
              AND
                  v.shop_id = :shopId

              WHERE
                  e.name = :elementName
            ";
        if (!empty($formName) && $formName !== self::SHOPWARE_GENERAL) {
            $sql .= "AND f.name = :formName";
            $sqlParameters['formName'] = $formName;
        } else {
            $sql .= "AND f.name IS NULL";
        }
        $sqlParameters['elementName'] = $elementName;
        $sqlParameters['shopId'] = $shopId;
        return Shopware()->Db()->fetchAll($sql, $sqlParameters);
    }

    /**
     * @param XMLImporter $importer
     * @param string[] $shopIds
     */
    private function readShopConfig($importer, $shopIds) {
        $lastShopId = null;
        foreach ($importer->getList('Config') as $xmlForm) {
            $shopId = (int) (string) $xmlForm['shop'];

            if ($shopIds && !in_array($shopId, $shopIds)) {
                continue;
            }
            if ($shopId != $lastShopId) {
                $lastShopId = $shopId;
                $this->printComment("\n  [config for shop ID: $shopId]\n");
            }

            $importer->changeCursor($xmlForm);
            foreach ($importer->getList('Value') as $xmlElement) {
                $result = $this->getExistingElement((int) $shopId, (String) $xmlForm['name'], (String) $xmlElement['name']);
                if (count($result) < 1) {
                    $this->printError("Error: could not find matching form:element combination for " . $xmlForm['name'] . ":" . $xmlElement['name'] . "  >Skipped\n");
                    continue;
                }elseif(count($result) > 1){
                    $this->printError("Error: found more than one form:element combination for " . $xmlForm['name'] . ":" . $xmlElement['name'] . "  >Skipped\n");
                    continue;
                }

                // so the config element exists
                $dbElement = $result[0];
                if (empty($dbElement['elementId'])) {
                    $this->printError("Error: Element ID is empty of element " . $xmlForm['name'] . ":" . $xmlElement['name'] . "  >Skipped \n");
                    continue;
                }

                $this->updateExistingElement(
                    (int) $shopId,
                    $dbElement,
                    (String) $xmlElement,
                    (String) $xmlForm['name'],
                    (String) $xmlElement['name'],
                    $xmlElement['inherited']=='true'?true:false
                );
            }
            $importer->cursorOut();
        }
    }


    /**
     * {@inheritdoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        parent::execute($input, $output);
        //target shop id
        if ($input->getOption('shop') !== null) {
            // option given
            $targetShopIds = explode(',', $input->getOption('shop'));
        } else {
            $targetShopIds = [];
            // import for all shop ids
            // get available shop ids
            //$targetShopIds = $this->manager->getShopIds();
        }

        $finder = new Finder();

        /** @var Finder[] $files */
        $files = $finder->files()->in($this->path)->name('*.xml');

        /** @var  \Symfony\Component\Finder\SplFileInfo $file */
        foreach ($files as $file) {
            try {
                $importer = new MndConfigImporter($file->getPathname());
                $this->printInfo("\nimport file " . $file->getPathname());
                $this->readShopConfig($importer, $targetShopIds);
            } catch (WrongRootException $e) {
                // ignore files with wrong root tag
            } catch (MismatchedVersionException $e) {
                // wrong version of plugin or shopware
                $this->printError('unmatched version! skipped file ' . $file->getPathname());
            }
        }
    }
}