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

use League\Flysystem\Exception;
use ShopwarePlugins\MndConfigExportImport\Components\MndConfigExporter;
use ShopwarePlugins\MndConfigExportImport\Components\XMLExporter;
use Shopware\Models\Plugin\Plugin;

use ShopwarePlugins\MndConfigExportImport\Utils\MndConfigIO;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Class ExportPluginCommand
 * @package Shopware\Plugin\MndConfigExportImport\Commands
 */
class ExportPluginCommand extends MndExportCommand
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
    protected function configure()
    {
        parent::configure();

        $this
            ->setName('mnd:sw'.$this->getCommandName().':save')
            ->setDescription('Save current state of plugins to directory ' . $this->getDefaultPath() . ' or custom target via -p');
    }

    /**
     * {@inheritdoc}
     */
    public function getCommandName() {
        return "plugin";
    }

    /**
     * {@inheritdoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        parent::execute($input, $output);

        $exporter = new MndConfigExporter();

        $pluginRepository = Shopware()->Models()->getRepository('Shopware\Models\Plugin\Plugin');
        $plugins = $pluginRepository->findBy(
            array(
                'capabilityEnable' => 1
            )
        );

        // sorting to get a comparable file for every system, sort by name, case insensitiv
        usort($plugins, function(Plugin $a, Plugin $b)    {
            $al = strtolower($a->getName());
            $bl = strtolower($b->getName());
            if ($al == $bl) {
                return 0;
            }
            return ($al > $bl) ? +1 : -1;
        });

        /* @var Plugin $plugin */
        foreach ($plugins as $plugin) {
            $exporter->newElement('Plugin');
            $exporter->addAttribute('name', $plugin->getName());
            $exporter->addAttribute('label', $plugin->getLabel());
            if ($plugin->getActive()) {
                $state = self::STATE_ACTIVE;
            } elseif ($plugin->getInstalled()) {
                $state = self::STATE_DEACTIVATED;
            } else {
                $state = self::STATE_UNINSTALLED;
            }
            $exporter->addAttribute('state', $state);
            //$exporter->addAttribute('active', (int) $plugin->getActive());
            //$exporter->addAttribute('installed', $plugin->getInstalled()?1:0);
        }

        try {
            $io = new MndConfigIO($this->getDefaultPath(), $input->getOptions());
            $io->setModulePath($this->getCommandName());
            $this->write($io, self::CONFIG_FILE, $exporter->get(), true);
        } catch (\Exception $e) {
            $this->printError($e->getMessage());
        }

    }
}