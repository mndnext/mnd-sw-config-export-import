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

namespace ShopwarePlugins\MndConfigExportImport\Utils;

use Symfony\Component\Filesystem\Exception\FileNotFoundException;
use Symfony\Component\Filesystem\Exception\IOException;
use Symfony\Component\Filesystem\Filesystem;

/**
 * Provides path handling and write/copy functionality
 */
class MndConfigIO
{

    private $basePath = null; // base path
    private $modulePath = null; //module path relative to base path
    private $subPath = null; // sub path relative to module path
    private $hideNullValues = false;
    private $mute = false;
    private $clean = false;
    private $lastFile = '';

    /** @var  \Symfony\Component\Filesystem\Filesystem $fs */
    private $fs;


    /**
     * MndConfigIO constructor.
     * @param string[] $options
     *              path: absolute path to be used, or empty
     *              clean: if true, module-directories will be recreated
     *              hide-null-values: if true, null values will not be written?!
     * @throws \Exception
     */
    public function __construct($defaultBase, $options)
    {
        if (isset($options['path']) && $options['path']) {
            $base = $options['path'];
        } else {
            $base = $defaultBase;
        }

        $this->fs = new Filesystem();
        if ($this->fs->isAbsolutePath($base) !== true) {
            throw new \Exception("Path $base is not absolute.");
        }
        $lastChar = substr($base, -1);
        if ($lastChar != "/") {
            $base .= "/";
        }
        $this->basePath = $base;

        if (isset($options['clean']) && $options['clean']) {
            $this->clean = true;
        }
        if (isset($options['hide-null-values']) && $options['hide-null-values']) {
            $this->hideNullValues = true;
        }
        if (isset($options['mute']) && $options['mute']) {
            $this->mute = true;
        }
    }

    /**
     * @param $path
     * @throws \Exception
     */
    public function setModulePath($path)
    {
        if ($this->fs->exists($this->basePath . $path) && !$this->clean) {
            throw new \Exception("Path '" . $this->basePath . $path . "' already exists. If you want to overwrite files add parameter '-c' for clean export.");
        }
        $lastChar = substr($path, -1);
        if ($lastChar != "/") {
            $path .= "/";
        }
        $this->modulePath = $path;

        if ($this->fs->exists($path) && $this->clean) {
            $this->fs->remove($path);
        }
    }

    /**
     *
     * @param $path
     * @throws \Exception
     */
    public function setSubPath($path)
    {
        if ($this->fs->exists($this->basePath . $this->modulePath . $path) && !$this->clean) {
            throw new \Exception("Path '" . $this->basePath . $this->modulePath . $path . "' already exists. If you want to overwrite files add parameter '-c' for clean export.");
        }
        if ($path != '') {
            $lastChar = substr($path, -1);
            if ($lastChar != "/") {
                $path .= "/";
            }
        }
        $this->subPath = $path;
    }

    public function getSubPath()
    {
        return $this->subPath;
    }

    /**
     * @param $filename
     * @param $content
     * @throws IOException If the file cannot be written to.
     */
    public function write($filename, $content)
    {
        if (empty($this->basePath)) {
            throw new IOException("Can't write file: path is empty!");
        }

        if (empty($filename)) {
            throw new IOException("Can't write file: filename is empty!");
        }

        if ($this->hideNullValues) {
            $content = preg_replace("/\s*'.*' => (''|NULL),/", "", $content);
        }

        $this->fs->dumpFile($this->basePath . $this->modulePath . $this->subPath . $filename, $content);
        $this->lastFile = $this->basePath . $this->modulePath . $this->subPath . $filename;
    }

    public function getLastFile()
    {
        return $this->lastFile;
    }

    /**
     * @param string $sourceFile absolute path and filename of source
     * @param string $filename destination filename, path will be basepath/modulepath/subpath/filename
     *
     * @throws FileNotFoundException When originFile doesn't exist
     * @throws IOException           When copy fails
     */
    public function copy($sourceFile, $filename)
    {
        if (empty($this->basePath)) {
            throw new IOException("Can't copy file: path is empty!");
        }

        if (empty($filename)) {
            throw new IOException("Can't copy file: filename is empty!");
        }

        $this->fs->copy(
            $sourceFile,
            $this->basePath . $this->modulePath . $this->subPath . $filename,
            true
        );
    }

    /**
     * @return mixed
     */
    public function getPath()
    {
        $path = $this->path;

        if (empty($path)) {
            return null;
        }


        $lastChar = substr($path, -1);
        if ($lastChar != "/") {
            $path .= "/";
        }
        return $path;
    }

    /**
     * @param $filename
     * @return bool
     */
    public function exists($filename) {
        if ($this->clean) {
            return false;
        }
        return $this->fs->exists($this->basePath . $this->modulePath . $this->subPath . $filename);
    }
}