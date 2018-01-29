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


class XMLExporter
{
    const LINEBREAK_WINDOWS = '\r\n';
    const LINEBREAK_MAC = '\n';
    const LINEBREAK_LINUX = '\r';

    const TYPE_BOOL = "boolean";
    const TYPE_INT = "integer";
    const TYPE_DOUBLE = "double";
    const TYPE_STRING = "string";
    const TYPE_ARRAY = 'array';
    const TYPE_OBJECT = "object";
    const TYPE_RESOURCE = "resource";
    const TYPE_NULL = "NULL";
    const TYPE_UNKNOWN = "unknown type";

    // constants used as parameter for setEmptyNode()
    const EMPTYNODE_HIDE = 0;    // empty nodes will be removed from xml (default)
    const EMPTYNODE_SINGLE = 1;  // empty nodes will have single tag <Node/>
    const EMPTYNODE_FULL = 2;    // empty nodes will have full open and close tag <Node></Node>
    const EMPTYNODE_COMMENT = 3; // empty nodes will have a comment inside <Node><!-- empty --></Node>

    private $xml;
    private $open = false;
    private $finished = false;
    private $emptyNode = self::EMPTYNODE_HIDE;

    public function __construct($root) {
        $this->xml = new \XMLWriter();
        $this->xml->openMemory();
        $this->xml->setIndent(true);
        $this->xml->setIndentString('    ');
        $this->xml->startDocument('1.0', 'UTF-8');
        $this->xml->startElement($root);
    }

    /**
     * @param int $node possible constants are EMPTYNODE_XXX
     */
    public function setEmptyNodes($node = self::EMPTYNODE_SINGLE) {
        if (!$node) {
            $node = self::EMPTYNODE_HIDE;
        }
        $this->emptyNode = $node;
    }

    public function newElement($name) {
        if ($this->open) {
            $this->xml->endElement();
        }
        $this->xml->startElement($name);
        $this->open = true;
    }

    public function finishElement() {
        if ($this->open) {
            $this->xml->endElement();
            $this->open = false;
        }
    }

    public function startList($name) {
        $this->xml->startElement($name);
        $this->open = false;
    }

    public function finishList() {
        if ($this->open) {
            $this->open = false;
            $this->xml->endElement();
        }
        $this->xml->endElement();
        $this->open = true;
    }

    public function add($nodeName, $text, $cdata = false, $attributes = []) {
        if (!$this->open) {
            return false;
        }

        if ($text === '' && empty($attributes) && !$this->emptyNode) {
            return true;
        }

        $this->xml->startElement($nodeName);
        foreach ($attributes as $name => $value) {
            $this->xml->writeAttribute($name, $value);
        }
        if ($text !== '') {
            if ($cdata) {
                // special case: as cdata linebreaks will not be escaped but converted
                // to preserve linbreaks an attribute will be added and read in XMLImporter
                if (strpos("\r\n", $text)) {
                    $this->xml->writeAttribute('linebreak', self::LINEBREAK_WINDOWS);
                } elseif (strpos("\r", $text)) {
                    $this->xml->writeAttribute('linebreak', self::LINEBREAK_LINUX);
                }

                //$this->xml->startCData();
                $this->xml->writeCData($text);
                //$this->xml->endCData();
            } else {
                $this->xml->text($text);
            }
        } elseif ($this->emptyNode == self::EMPTYNODE_COMMENT) {
            $this->xml->writeRaw('<!-- empty -->');
        } elseif ($this->emptyNode == self::EMPTYNODE_FULL) {
            $this->xml->text('');
        }
        $this->xml->endElement();
        return true;
    }

    public function addProp($object, $prop, $cdata = false) {
        if (!$this->open) {
            return false;
        }

        $getFunc = 'get' . $prop;
        if (method_exists($object, $getFunc)) {
            $value = $object->$getFunc();
            switch (gettype($value)) {
                case self::TYPE_BOOL:
                    $value = $value?'1':'0';
                    break;
                case self::TYPE_INT:
                case self::TYPE_DOUBLE:
                case self::TYPE_STRING:
                    $value = (string) $value;
                    break;
                case self::TYPE_NULL:
                    $value = '';
                    break;
                default:
                    $value = serialize($value);
            }
            return $this->add($prop, $value, $cdata);
        }
        return false;
    }

    /**
     * add every element from array as a node
     * <nodeName index="key">value</nodeName>
     *   or for associative arrays
     * <nodeName key="key">value</nodeName>
     *
     * @param string $nodeName
     * @param array $array
     */
    public function addArray($nodeName, $array) {
        if (!empty($array)) {
            foreach ($array as $key => $value) {
                $this->xml->startElement($nodeName);
                if (is_int($key)) {
                    $this->xml->writeAttribute('index', $key);
                } else {
                    $this->xml->writeAttribute('key', $key);
                }
                if (gettype($value) !== self::TYPE_STRING) {
                    $this->xml->writeAttribute('type', gettype($value));
                }
                switch (gettype($value)) {
                    case self::TYPE_ARRAY:
                        $this->addArray('Element', $value);
                        break;
                    case self::TYPE_OBJECT:
                    case self::TYPE_UNKNOWN:
                    case self::TYPE_RESOURCE:
                        break;
                    default:
                        $this->xml->text($value);
                        break;
                }
                $this->xml->endElement();
            }
        }
    }

    /**
     * add associative array to xml-tree
     * preserves element types with XMLImporter->getAssocArray()
     * keys will be the node names and values will be node text.
     *
     * @param $array
     */
    public function addAssocArray($array) {
        if (!empty($array)) {
            foreach ($array as $key => $value) {
                $this->xml->startElement($key);
                if (gettype($value) !== self::TYPE_STRING) {
                    $this->xml->writeAttribute('type', gettype($value));
                }
                switch (gettype($value)) {
                    case self::TYPE_OBJECT:
                        $this->xml->writeAttribute('class', get_class($value));
                        $this->addAssocArray($value);
                        break;
                    case self::TYPE_ARRAY:
                        $this->addAssocArray($value);
                        break;
                    case self::TYPE_UNKNOWN:
                    case self::TYPE_RESOURCE:
                        break;
                    default:
                        $this->xml->text($value);
                        break;
                }
                $this->xml->endElement();
            }
        }
    }

    /**
     * works only if it is called directly after startList() or newElement()
     * calls to other methods from this class will let addAttribute() fail after them!
     *
     * @param $attribute
     * @param $value
     */
    public function addAttribute($attribute, $value) {
        if ((string) $value !== '') {
            $this->xml->writeAttribute($attribute, $value);
        }
    }

    /**
     * Add comment <!-- --> block, when $comment is over 50 characters, will auto. wrap
     *
     * @param string $comment
     */
    public function addComment($comment) {
        $this->xml->writeComment($comment);
    }

    /**
     * to be able to set special "linebreak"-attribute, setText() has to be
     * called before adding child-elements and has not to be called twice.
     *
     * @param string $text
     * @param bool|null $cdata
     */
    public function setText($text, $cdata = false) {
        if (!$text) {
            return;
        }
        if ($cdata) {
            // special case: as cdata linebreaks will not be escaped but converted
            // to preserve linbreaks an attribute will be added and read in XMLImporter
            if (strpos("\r\n", $text)) {
                $this->xml->writeAttribute('linebreak', self::LINEBREAK_WINDOWS);
            } elseif (strpos("\r", $text)) {
                $this->xml->writeAttribute('linebreak', self::LINEBREAK_LINUX);
            }
            $this->xml->writeCData($text);
        } elseif ($cdata === null) {
            //$text = str_replace("\r", '', $text);
            $this->xml->writeRaw($text);
        } else {
            $this->xml->text($text);
        }
    }

    /**
     * returns xml-tree as string
     *
     * @return string
     */
    public function get() {
        if (!$this->finished) {
            $this->xml->endElement();
            $this->xml->endDocument();
            $this->finished = true;
        }
        return $this->xml->outputMemory();
    }

    /**
     * @TODO stub
     *
     * @return array
     */
    public function getErrors() {
        return [];
    }
}