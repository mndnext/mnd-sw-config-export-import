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


abstract class MndManager {
    protected $silence;
    private $errors;

    function __construct($options){
        if(isset($options['quiet'])){
            $this->silence = $options['quiet'];
        }

        $this->errors = array();
        $this->init();
    }

    abstract function init();

    protected function printMessage($message){
        if(!$this->silence){
            echo $message;
        }
    }

    protected function printError($error){
        $this->errors[md5($error)] = $error;

        if(!$this->silence){
            echo $error;
        }
    }

    public function getErrors(){
        return array_values($this->errors);
    }
}