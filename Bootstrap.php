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

class Shopware_Plugins_Core_MndConfigExportImport_Bootstrap extends Shopware_Components_Plugin_Bootstrap
{
    /**
     * Returns the information of the plugin
     * with will be displayed in the plugin manager
     *
     * @return array
     */
    public function getInfo()
    {
        return json_decode(file_get_contents(__DIR__ . '/plugin.json'), true);
    }

    /**
     * Installs the plugin.
     *
     * @return boolean
     */
    public function install()
    {
        try {
            $this->subscribeEvents();

        } catch (Exception $e) {
            return array('success' => false, 'message' => $e->getMessage());
        }

        return true;
    }

    /**
     * Uninstalls the plugin.
     *
     * @return boolean
     */
    public function uninstall()
    {
        return array(
            'success'         => true
        );
    }

    /**
     * Starts the update of the plugin
     */
    public function update($version)
    {
        $this->install();
        return true;
    }

    /**
     * Registers all events with are required for the plugin
     */
    protected function subscribeEvents()
    {
        $this->subscribeEvent(
            'Shopware_Console_Add_Command',
            'onAddConsoleCommand'
        );
    }

    public function afterInit()
    {
        $this->get('Loader')->registerNamespace(
            'ShopwarePlugins\\MndConfigExportImport',
            __DIR__ . '/'
        );
    }


    // Just register one single command
    public function onAddConsoleCommand(Enlight_Event_EventArgs $args)
    {
            return new \Doctrine\Common\Collections\ArrayCollection(array(
                new ShopwarePlugins\MndConfigExportImport\Commands\ExportAllCommand(),

                new ShopwarePlugins\MndConfigExportImport\Commands\ExportConfigCommand(),
                new ShopwarePlugins\MndConfigExportImport\Commands\ImportConfigCommand(),

                new ShopwarePlugins\MndConfigExportImport\Commands\ExportMailCommand(),
                new ShopwarePlugins\MndConfigExportImport\Commands\ImportMailCommand(),

                new ShopwarePlugins\MndConfigExportImport\Commands\ExportProductFeedCommand(),
                new ShopwarePlugins\MndConfigExportImport\Commands\ImportProductFeedCommand(),

                new ShopwarePlugins\MndConfigExportImport\Commands\ExportThemeConfigCommand(),
                new ShopwarePlugins\MndConfigExportImport\Commands\ImportThemeConfigCommand(),

                new ShopwarePlugins\MndConfigExportImport\Commands\ExportCmsCommand(),
                new ShopwarePlugins\MndConfigExportImport\Commands\ImportCmsCommand(),

                new ShopwarePlugins\MndConfigExportImport\Commands\ExportFormCommand(),
                new ShopwarePlugins\MndConfigExportImport\Commands\ImportFormCommand(),

                new ShopwarePlugins\MndConfigExportImport\Commands\ExportDocumentCommand(),
                new ShopwarePlugins\MndConfigExportImport\Commands\ImportDocumentCommand(),

                new ShopwarePlugins\MndConfigExportImport\Commands\ExportPluginCommand(),
                new ShopwarePlugins\MndConfigExportImport\Commands\ImportPluginCommand(),
            ));

    }

 }
