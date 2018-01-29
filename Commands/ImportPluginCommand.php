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

use Shopware\Bundle\PluginInstallerBundle\Service\InstallerService;
use ShopwarePlugins\MndConfigExportImport\Components\MndConfigImporter;
use ShopwarePlugins\MndConfigExportImport\Exception\MismatchedVersionException;
use ShopwarePlugins\MndConfigExportImport\Exception\WrongRootException;

use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Finder\Finder;

/**
 * Class ImportPluginCommand
 * @package ShopwarePlugins\MndConfigExportImport\Commands
 */
class ImportPluginCommand extends MndImportCommand
{
    const STATE_UNINSTALLED = 'uninstalled';
    const STATE_DEACTIVATED = 'deactivated';
    const STATE_ACTIVE = 'active';
    const STATE_VALUES = [
        0 => self::STATE_UNINSTALLED,
        1 => self::STATE_DEACTIVATED,
        2 => self::STATE_ACTIVE
    ];

    /**
     * {@inheritdoc}
     */
    protected function configure() {
        parent::configure();

        $this
            ->setName('mnd:sw'.$this->getCommandName().':restore')
            ->setDescription('Restore plugin states from directory ' . $this->getDefaultPath() . ' or custom source via -p');
    }

    /**
     * {@inheritdoc}
     */
    public function getCommandName() {
        return "plugin";
    }

    /**
     * @param MndConfigImporter $importer
     */
    private function importPluginSettings($importer) {
        /** @var InstallerService $pluginManager */
        $pluginManager  = $this->container->get('shopware_plugininstaller.plugin_manager');

        foreach ($importer->getList('Plugin') as $xmlPlugin) {
            $name = (string) $xmlPlugin['name'];
            $installed = (bool) (string) $xmlPlugin['installed'];
            $active = (bool) (string) $xmlPlugin['active'];

            $state = (string) $xmlPlugin['state'];
            if (is_numeric($state)) {
                $state = (int) $state;
                if ($state < 0 || $state > count(self::STATE_VALUES)) {
                    $this->printImportant("unknown state ($state) for plugin <comment>$name</comment>");
                    continue;
                }
                $state = self::STATE_VALUES[$state];
            }
            switch ($state) {
                case self::STATE_ACTIVE:
                    $installed = true;
                    $active = true;
                    break;
                case self::STATE_DEACTIVATED:
                    $installed = true;
                    $active = false;
                    break;
                case self::STATE_UNINSTALLED:
                    $installed = false;
                    $active = false;
                    break;
                default:
                    $this->printImportant("unknown state ".'"'.$state.'"'." for plugin <comment>$name</comment>");
                    continue;
            }


            try {
                $plugin = $pluginManager->getPluginByName($name);
            } catch (\Exception $e) {
                $this->printMessage(sprintf('Plugin <comment>%s</comment> <error>not found</error>.', $name));
                continue;
            }

            //Deinstallieren
            if ($plugin->getInstalled() && !$installed) {
                $pluginManager->uninstallPlugin($plugin);
                $this->printMessage(sprintf('Plugin <comment>%s</comment> uninstalled.', $name));
                continue; //Nach einer deinstallation muss nicht nach aktivierung gefragt werden
            }

            //Installieren
            if (!$plugin->getInstalled() && $installed) {
                $pluginManager->installPlugin($plugin);
                $this->printMessage(sprintf('Plugin <comment>%s</comment> <info>installed</info>.', $name));
            }

            //Deactiviert
            if ($plugin->getActive() && !$active) {
                $pluginManager->deactivatePlugin($plugin);
                $this->printMessage(sprintf('Plugin <comment>%s</comment> deactivated.', $name));
            }

            //Activiert
            if (!$plugin->getActive() && $active) {
                $pluginManager->activatePlugin($plugin);
                $this->printMessage(sprintf('Plugin <comment>%s</comment> <info>activated</info>.', $name));
            }
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
            try {
                $importer = new MndConfigImporter($file->getPathname());
                $this->printInfo("\nimport file " . $file->getPathname());
                $this->importPluginSettings($importer);
            } catch (WrongRootException $e) {
                // wrong root tag
                continue;
            } catch (MismatchedVersionException $e) {
                // wrong version of plugin or shopware
                $this->printError('unmatched version! skipped file ' . $file->getPathname());
                continue;
            }
        }
    }
}