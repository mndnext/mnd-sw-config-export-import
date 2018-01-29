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
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

abstract class MndImportCommand extends MndCommand
{
    protected $path = null;

    /**
     * {@inheritdoc}
     */
    protected function configure()
    {
        parent::configure();

        $this->setName('mnd:sw'.$this->getCommandName().':import');
    }

    /**
     * {@inheritdoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output) {
        parent::execute($input, $output);

        if ($input->getOption('path')) {
            $this->path = $input->getOption('path');
        } else {
            $this->path = $this->getDefaultPath();
        }

        $lastChar = substr($this->path,-1);
        if ($lastChar == "/") {
            $this->path = substr($this->path, 0,strlen($this->path) - 1);
        }
        if (substr($this->path, -strlen($this->getCommandName())) != $this->getCommandName()) {
            $this->path .= '/' . $this->getCommandName();
        }
    }


    protected function getFullPath($file) {
        return $this->path . $file;
    }

}

