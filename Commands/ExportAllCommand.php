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

use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Class ExportAllCommand
 * @package Shopware\Plugin\MndConfigExportImport\Commands
 */
class ExportAllCommand extends MndExportCommand
{

    /**
     * {@inheritdoc}
     */
    protected function configure()
    {
        parent::configure();

        $this
            ->setName('mnd:sw'.$this->getCommandName().':export')
            ->setDescription('Export all to directory ' . $this->getDefaultPath() . ' or custom target via -p');
    }

    /**
     * {@inheritdoc}
     */
    public function getCommandName()
    {
        return "all";
    }

    function getErrors()
    {
        return null;
    }


    /**
     * {@inheritdoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        parent::execute($input, $output);

        $args = str_replace("'".$input->getArgument('command')."'", "", $input->__toString());

        $export = new ExportCmsCommand();
        $export->run(new \Symfony\Component\Console\Input\StringInput($args), new \Symfony\Component\Console\Output\ConsoleOutput());

        $export = new ExportConfigCommand();
        $export->run(new \Symfony\Component\Console\Input\StringInput($args), new \Symfony\Component\Console\Output\ConsoleOutput());

        $export = new ExportDocumentCommand();
        $export->run(new \Symfony\Component\Console\Input\StringInput($args), new \Symfony\Component\Console\Output\ConsoleOutput());

        $export = new ExportFormCommand();
        $export->run(new \Symfony\Component\Console\Input\StringInput($args), new \Symfony\Component\Console\Output\ConsoleOutput());

        $export = new ExportMailCommand();
        $export->run(new \Symfony\Component\Console\Input\StringInput($args), new \Symfony\Component\Console\Output\ConsoleOutput());

        $export = new ExportPluginCommand();
        $export->run(new \Symfony\Component\Console\Input\StringInput($args), new \Symfony\Component\Console\Output\ConsoleOutput());

        $export = new ExportProductFeedCommand();
        $export->run(new \Symfony\Component\Console\Input\StringInput($args), new \Symfony\Component\Console\Output\ConsoleOutput());

        $export = new ExportThemeConfigCommand();
        $export->run(new \Symfony\Component\Console\Input\StringInput($args), new \Symfony\Component\Console\Output\ConsoleOutput());
    }
}