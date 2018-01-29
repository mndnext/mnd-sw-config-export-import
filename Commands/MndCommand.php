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

abstract class MndCommand extends ShopwareCommand {
    const CONFIG_FILE = "config.xml";

    private $defaultPath;
    protected $mute = false;
    protected $shopwareVersion = null;
    /* @var OutputInterface $output */
    protected $output = null;

    function __construct($name = null) {
        parent::__construct($name = null);
        $this->shopwareVersion = Shopware()->Config()->get("version");
    }

    protected function printMessage($msg) {
        if ($this->mute) {
            return;
        }
        $this->output->writeln($msg);
    }

    protected function printInfo($msg) {
        if ($this->mute) {
            return;
        }
        $this->output->writeln("<info>$msg</info>");
    }

    protected function printComment($msg) {
        if ($this->mute) {
            return;
        }
        $this->output->writeln("<comment>$msg</comment>");
}

    protected function printError($msg) {
        if ($this->mute) {
            return;
        }

        $lines = explode("\n", $msg);
        $len = 0;
        foreach ($lines as $line) {
            if ($len < strlen($line)) {
                $len = strlen($line);
            }
        }
        $this->output->writeln("<error>  ".str_repeat(' ', $len).'  </error>');
        foreach ($lines as $line) {
            $line = str_pad($line, $len);
            $this->output->writeln("<error>  $line  </error>");
        }
        $this->output->writeln("<error>  ".str_repeat(' ', $len).'  </error>');
    }

    protected function printImportant($msg) {
        if ($this->mute) {
            return;
        }
        $this->output->writeln("<fg=red;option=bold>$msg</>");
    }

    protected function printNote($msg) {
        if ($this->mute) {
            return;
        }
        $this->output->writeln("<fg=blue>$msg</>");
    }

    protected function configure() {
        $this->defaultPath = Shopware()->DocPath() . '_mndExport/';

        $this->addOption(
            'path',
            'p',
            InputOption::VALUE_REQUIRED,
            'set target/source directory (absolute path)'
        );
        $this->addOption(
            'mute',
            'm',
            InputOption::VALUE_NONE,
            'no output'
        );
    }

    protected function getDefaultPath() {
        return $this->defaultPath;
    }


    protected function execute(InputInterface $input, OutputInterface $output) {
        $this->mute = $input->getOption('mute');
        $this->output = $output;
    }

    abstract function getCommandName();
}