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

use ShopwarePlugins\MndConfigExportImport\Exception\FileIsEmptyException;
use ShopwarePlugins\MndConfigExportImport\Exception\WrongRootException;
use ShopwarePlugins\MndConfigExportImport\Exception\XMLException;
use Symfony\Component\Filesystem\Exception\FileNotFoundException;

class XMLImporter
{
    const LINEBREAK_WINDOWS = '\r\n';
    const LINEBREAK_MAC = '\n';
    const LINEBREAK_LINUX = '\r';

    const TYPE_BOOL = 'boolean';
    const TYPE_INT = 'integer';
    const TYPE_DOUBLE = 'double';
    const TYPE_STRING = 'string';
    const TYPE_ARRAY = 'array';
    const TYPE_OBJECT = "object";
    const TYPE_RESOURCE = "resource";
    const TYPE_NULL = "NULL";
    const TYPE_UNKNOWN = "unknown type";

    private $xml;
    private $cursor;
    private $history = [];
    private $path;

    /**
     * XMLImporter constructor.
     * @param $filePath
     * @param $root
     *
     * @throws FileNotFoundException if file not found
     * @throws FileIsEmptyException if file is empty
     * @throws XMLException  if not valid xml file
     * @throws WrongRootException if wrong root node
     * @throws \Exception
     */
    public function __construct($filePath, $root) {
        $this->path = dirname($filePath) . '/';
        $this->xml = $this->parseXml($filePath, $filePath);
        $this->preserveLineBreaks($this->xml);
        if ($this->xml->getName() != $root) {
            // wrong root node
            throw new WrongRootException('XML-Node "' . $root . '" expected, but got "' . $this->xml->getName() . '"!');
        }
    }

    /**
     * @param string $filePath
     * @return \SimpleXMLElement
     *
     * @throws FileNotFoundException if file not found
     * @throws FileIsEmptyException if file is empty
     * @throws XMLException  if not valid xml file
     * @throws \Exception
     */
    private function parseXml($filePath) {
        if (!file_exists($filePath)) {
            throw new FileNotFoundException('file ' . $filePath . ' not found!');
        }
        $content = file_get_contents($filePath);
        if (empty($content)) {
            throw new FileIsEmptyException('file ' . $filePath . ' is empty!');
        }
        $use_errors = libxml_use_internal_errors(true);
        try {
            $xmlRoot = new \SimpleXMLElement($content, LIBXML_NOCDATA);
            libxml_clear_errors();
            libxml_use_internal_errors($use_errors);
        } catch (\Exception $e) {
            $errors = libxml_get_errors();
            libxml_clear_errors();
            libxml_use_internal_errors($use_errors);
            if (!empty($errors)) {
                $message = "file: $filePath\n";
                foreach ($errors as $error) {
                    if ($message) $message .= "\n";
                    $message .= $error->message;
                }
                throw new XMLException($message);
            } else {
                throw $e;
            }
        }

        return $xmlRoot;
    }


    private function preserveLineBreaks($xml) {
        foreach ($xml as $key => $value) {
            if (count($value)) {
                $this->preserveLineBreaks($value);
            } elseif (isset($value['linebreak'])) {
                switch ($value['linebreak']) {
                    case self::LINEBREAK_WINDOWS:
                        $value[0] = str_replace("\n", "\r\n", $value[0]);
                        break;
                    case self::LINEBREAK_LINUX:
                        $value[0] = str_replace("\n", "\r", $value[0]);
                        break;
                    case self::LINEBREAK_MAC:
                    default:
                        //$value[0] = str_replace("\n", "\n", $value[0]);
                        break;
                }
                unset($value['linebreak']);
            }
        }
    }

    /**
     * Change current node to given node.
     * if parameter $fileImporter is set to true, the node can have an attribute "file" where
     * the xml-tree is outsourced. It will try to load this file and changes the current node to the root node
     * of that external xml-file. if attribute "file" is given there is an error, an exception will be thrown
     *
     * @param \SimpleXMLElement $xmlNode
     * @param bool $fileImport
     * @return string|bool if $fileImport is true and a extern file is imported, will return full path
     *                      if a "file"-attribute is given but couldn't load that file, will return false.
     *                      Otherwise will use the given node and returns true.
     *
     * @throws XMLException       if $fileImport = true and not valid xml file
     * @throws WrongRootException if $fileImport = true and has wrong root node
     */
    public function changeCursor($xmlNode, $fileImport = false) {
        if ($this->cursor) {
            $this->history[count($this->history)] = $this->cursor;
        }
        $this->cursor = $xmlNode;

        if ($fileImport && $xmlNode['file']) {
            $file = $this->path . (string) $xmlNode['file'];
            if (substr($file, -3) === 'xml' && !is_dir($file)) {
                $xmlExtern = $this->parseXml($file);
                if ($xmlExtern->getName() != $xmlNode->getName()) {
                    throw new WrongRootException("file $file\n" . 'XML-Node "' . $xmlNode->getName() . '" expected, but got "' . $xmlExtern->getName() . '"!');
                }
                $this->cursor = $xmlExtern;
                return $file;
            }
        }

        return true;
    }

    /**
     * @param string|string[] $nodePath
     * @return bool
     */
    public function cursorInto($nodePath) {
        if ($this->cursor) {
            $cursor = $this->cursor;
        } else {
            $cursor = $this->xml;
        }

        if (is_array($nodePath)) {
            foreach ($nodePath as $n) {
                if (!isset($cursor->$n)) {
                    return false;
                }
                $cursor = $cursor->$n;
            }
        } else {
            if (!isset($cursor->$nodePath)) {
                return false;
            }
            $cursor = $cursor->$nodePath;
        }
        if ($this->cursor) {
            $this->history[count($this->history)] = $this->cursor;
        }
        $this->cursor = $cursor;
        return true;
    }

    /**
     *
     */
    public function cursorOut() {
        $count = count($this->history);
        if (!empty($this->history)) {
            $this->cursor = $this->history[$count - 1];
            unset($this->history[$count - 1]);
        } else {
            $this->cursor = null;
        }
    }

    /**
     * @param string $node
     * @return \SimpleXMLElement[]
     */
    public function getList($node = '') {
        if ($node == '') {
            if ($this->cursor) {
                return $this->cursor->children();
            } else {
                return $this->xml->children();
            }
        }

        if ($this->cursor) {
            if (isset($this->cursor->$node)) {
                return $this->cursor->$node;
            }
        } else {
            if (isset($this->xml->$node)) {
                return $this->xml->$node;
            }
        }
        return [];
    }

    /**
     * @param string $node
     * @return string
     */
    public function getText($node) {
        if ($this->cursor) {
            if (isset($this->cursor->$node)) {
                return (string)$this->cursor->$node;
            }
        } else {
            if (isset($this->xml->$node)) {
                return (string)$this->xml->$node;
            }
        }
        return '';
    }

    /**
     * @param string $node
     * @return string
     */
    public function getValue($node) {
        if ($this->cursor) {
            if (isset($this->cursor->$node)) {
                return (string)$this->cursor->$node;
            }
        } else {
            if (isset($this->xml->$node)) {
                return (string)$this->xml->$node;
            }
        }
        return '';
    }

    /**
     * Read a list of nodes and returns them as an array.
     * Nodes need to have an attribute "key" or "index".
     * Attribute "index" is expected to be an int.
     * The node text will be read as value.
     *
     * for example:
     * <$parentNode>
     *  <$node key="key">value</$node>
     *  <$node key="key">value</$node>
     *  <$node index="0">value</$node>
     *  <$node index="1">value</$node>
     * </$parentNode>
     *
     * @param string $node
     * @param string $parentNode (optional)
     * @return array|false
     */
    public function getArray($node, $parentNode = '') {
        $cursor = $this->cursor;
        if (!$cursor) {
            $cursor = $this->xml;
        }
        if ($parentNode) {
            if (!isset($cursor->$parentNode)) {
                return false;
            }
            $cursor = $cursor->$parentNode;
        }
        if (!isset($cursor->$node)) {
            return false;
        }
        $return = [];
        /* @var \SimpleXMLElement $element */
        foreach ($cursor->$node as $element) {
            if (!isset($element['type'])) {
                $element['type'] = self::TYPE_STRING;
            }
            switch((string) $element['type']) {
                case self::TYPE_ARRAY:
                    $this->changeCursor($element);
                    $value = $this->getArray('Element');
                    $this->cursorOut();
                    break;
                default:
                    $value = (string) $element;
                    settype($value, $element['type']);
                    break;
            }
            if (isset($element['key'])) {
                $return[(string)$element['key']] = $value;
            } elseif (isset($element['index'])) {
                $return[(int)$element['index']] = $value;
            }
        }
        return $return;
    }

    /**
     * Read a list of nodes and returns them as an associative array.
     * Will read child nodes of current node (or $node) where every node name will be
     * the element name and the node text is the element value. If attribute type is
     * given, will change variable type of that element.
     *
     * @param null|\SimpleXMLElement $node parent node for all array elements
     * @return array|bool
     */
    public function getAssocArray($node = null) {
        $cursor = $this->cursor;
        if (!$cursor) {
            $cursor = $this->xml;
        }
        if ($node) {
            if (!isset($cursor->$node)) {
                return false;
            }
            $cursor = $cursor->$node;
        }

        $array = [];
        /* @var \SimpleXMLElement $element */
        foreach ($cursor->children() as $element) {
            if (!isset($element['type'])) {
                $element['type'] = self::TYPE_STRING;
            }
            switch((string) $element['type']) {
                case self::TYPE_ARRAY:
                    $this->changeCursor($element);
                    $value = $this->getAssocArray();
                    $this->cursorOut();
                    break;
                case self::TYPE_OBJECT:
                    $class = (string) $element['class'];
                    $value = new $class($element->children());
                    break;
                default:
                    $value = (string) $element;
                    settype($value, $element['type']);
                    break;
            }
            $array[$element->getName()] = $value;
        }
        return $array;
    }

    /**
     * @param string $node
     * @param $attribute
     * @return string
     */
    public function getAttribute($node, $attribute) {
        if ($this->cursor) {
            if ($node) {
                if (isset($this->cursor->$node)) {
                    $attributes = $this->cursor->$node->attributes();
                    if ($attributes[$attribute]) {
                        return $attributes[$attribute];
                    }
                }
            } else {
                if (isset($this->cursor[$attribute])) {
                    return (string)$this->cursor[$attribute];
                }
            }
        } else {
            if ($node) {
                if (isset($this->xml->$node)) {
                    $attributes = $this->xml->$node->attributes();
                    if ($attributes[$attribute]) {
                        return $attributes[$attribute];
                    }
                    return (string)$this->xml->$node[$attribute];
                }
            } else {
                if (isset($this->xml[$attribute])) {
                    return (string)$this->xml[$attribute];
                }
            }
        }
        return '';
    }

    /**
     * @param mixed $object
     * @param string $prop
     * @param string $type
     */
    public function setProp($object, $prop, $type = self::TYPE_STRING) {
        if (isset($this->cursor->$prop)) {
            $setFunc = 'set' . $prop;
            if (method_exists($object, $setFunc)) {
                $value = (String) $this->cursor->$prop;
                if ($value === '' && $type != self::TYPE_STRING) {
                    $value = null;
                } elseif ($type == self::TYPE_ARRAY ||
                    $type == self::TYPE_OBJECT ||
                    $type == self::TYPE_RESOURCE ||
                    $type == self::TYPE_NULL ||
                    $type == self::TYPE_UNKNOWN
                ) {
                    $value = unserialize($value);
                } else {
                    settype($value, $type);
                }
                $object->$setFunc($value);
            }
        }
    }

    public function hasNode($node) {
        $cursor = $this->cursor;
        if (!$cursor) {
            $cursor = $this->xml;
        }
        return isset($cursor->$node);
    }

    /**
     * Read and return file content based of the given node.
     * If $attribute is given, will use its content as filename.
     * If not given, it will try to read attribute "file" and use it as filename.
     * If not found, it will use the node-text as filename.
     *
     * @param string $node
     * @param string $attribute
     * @param string $path
     * @return bool|string
     *
     * @throws FileNotFoundException if file not found
     */
    public function readFile($node = '', $attribute = '', $path = '')
    {
        $cursor = $this->cursor;
        if (!$cursor) {
            $cursor = $this->xml;
        }
        if ($node) {
            $cursor = $cursor->$node;
        }
        if ($attribute) {
            $filename = (string)$cursor[$attribute];
        } elseif ($cursor['file']) {
            $filename = (string)$cursor['file'];
        } else {
            $filename = (string)$cursor;
        }

        if (!$filename) {
            throw new FileNotFoundException('no value for filename given');
        }

        if ($path) {
            $lastChar = substr($path, -1);
            if ($lastChar != "/") {
                $path .= "/";
            }
        }

        $filename = $this->path . $path . $filename;
        if (!file_exists($filename) || is_dir($filename)) {
            throw new FileNotFoundException($filename);
        }
        return @file_get_contents($filename);
    }

    public function getPath() {
        return $this->path;
    }
}