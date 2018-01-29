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

use ShopwarePlugins\MndConfigExportImport\Utils\MndConfigIO;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Filesystem\Exception\FileNotFoundException;
use Symfony\Component\Filesystem\Exception\IOException;

abstract class MndExportCommand extends MndCommand
{
    protected function configure() {
        parent::configure();

        $this->addOption(
            'clean',
            'c',
            InputOption::VALUE_NONE,
            'Clean directories. WARNING: Whole base directory of export will be removed first!');

        $this->addOption(
            'skip-htaccess-file',
            null,
            InputOption::VALUE_NONE,
            'By default a .htacces file will be created at the root folder of the export. This parameter will skip this procedure.');

        $this->addOption(
            'hide-null-values',
            null,
            InputOption::VALUE_NONE,
            'Should entries like "value => NULL" to be hidden');
    }


    protected function execute(InputInterface $input, OutputInterface $output)
    {
        parent::execute($input, $output);

        /* Schreibt eine .htaccess Datei in den export Ordner */
        if(!$input->getOption('skip-htaccess-file')){
            $this->copyHtAccess($input);
        }else{
            if (!$this->mute){
                echo ".htaccess file has been skipped\n";
            }
        }
        $this->mute = $input->getOption('mute');
    }

    /**
     * @param InputInterface $input
     */
    private function copyHtAccess($input) {
        $io = new MndConfigIO($this->getDefaultPath(), $input->getOptions());
        // kopiert die _htaccess aus dem Plugin-Rootordner in das exportierende Verzeichnis
        $io->write('.htaccess', file_get_contents(__DIR__."../../Skeleton/_htaccess"));
    }

    /**
     * @param MndConfigIO $io
     * @param string $filename
     * @param string $content
     */
    protected function write($io, $filename, $content, $highlight = false) {
        try {
            $io->write($filename, $content);
            if (!$highlight) {
                $this->printMessage('file created ' . $io->getLastFile());
            } else {
                $this->printInfo('file created ' . $io->getLastFile());
            }
        } catch (IOException $e) {
            $this->printError($e->getMessage());
        }
    }

    /**
     * @param MndConfigIO $io
     * @param string $sourceFile
     * @param string $filename
     */
    protected function copy($io, $sourceFile, $filename) {
        try {
            $io->copy($sourceFile, $filename);
            $this->printMessage('file copied to ' . $io->getLastFile());
        } catch (FileNotFoundException $e) {
            $this->printError($e->getMessage());
        } catch (IOException $e) {
            $this->printError($e->getMessage());
        }
    }


    function objectToArray($data)
    {
        if (is_array($data) || is_object($data))
        {
            $result = array();
            foreach ($data as $key => $value)
            {
                $result[$key] = $this->objectToArray($value);
            }
            return $result;
        }
        return $data;
    }
}