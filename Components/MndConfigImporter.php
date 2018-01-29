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

namespace ShopwarePlugins\MndConfigExportImport\Components;


use ShopwarePlugins\MndConfigExportImport\Exception\MismatchedVersionException;
use ShopwarePlugins\MndConfigExportImport\Exception\WrongRootException;
use Symfony\Component\Filesystem\Exception\IOException;

class MndConfigImporter extends XMLImporter
{
    const ROOT_NODE = 'MndConfig';

    private function getVersion() {
        $plugin = json_decode(file_get_contents(__DIR__ . '/../plugin.json'), true);
        return $plugin['version'];
    }

    public function __construct($filepath, $root = null) {
        parent::__construct($filepath, self::ROOT_NODE);

        if (version_compare(Shopware()->Config()->get("version"),  $this->getAttribute('','shopware')) <> 0) {
            throw new MismatchedVersionException('Exportierte Daten stammen aus einer anderen Shopware Version!');
        }

        if (version_compare($this->getVersion(),  $this->getAttribute('','version')) <> 0) {
            throw new MismatchedVersionException('Exportiere Daten stammen aus einer anderen Plugin Version');
        }

        if ($root) {
            if (!$this->cursorInto($root)) {
                // wrong root-child node
                throw new WrongRootException('XML-Node "' . $root . '" expected, but could not be found!');
           }
        }
    }
}