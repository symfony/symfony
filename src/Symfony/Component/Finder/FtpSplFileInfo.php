<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Finder;

/**
 * FtpSplFileInfo implements \SplFileInfo
 * for files/directories accesible via ftp connection.
 *
 * @author Włodzimierz Gajda <gajdaw@gajdaw.pl>
 */
class FtpSplFileInfo extends \SplFileInfo
{

    const TYPE_UNKNOWN = 1;
    const TYPE_DIRECTORY = 2;
    const TYPE_FILE = 3;
    const TYPE_LINK = 4;

    private $type = self::TYPE_DIRECTORY;

    /**
     * The name of a directory is always .:
     *
     * Directory:    /lorem/ipsum/dolor
     * Path:         /lorem/ipsum/dolor
     * Filename:     .
     *
     *
     * File:         /lorem/ipsum/dolor/sit.txt
     * Path:         /lorem/ipsum/dolor
     * Filename:     sit.txt
     *
     */
    private $path = '/';
    private $filename = '.';

    /**
     * Constructor.
     *
     * Examples:
     *
     * $e = new FtpSplFileInfo('/this/is/a/dir');
     * $e = new FtpSplFileInfo('/and/this/is/a/file.txt', self::TYPE_FILE);
     *
     * @param type $item The name of the file or directory
     * @param type $type The type of the first parameter: directory or file
     */
    public function __construct($item = '/', $type = self::TYPE_DIRECTORY)
    {
        $this->setType($type);

        if ($type === self::TYPE_DIRECTORY) {
            $this->filename = '.';
            $this->path = $item;
        } else if ($type === self::TYPE_FILE) {
            $tmp = self::parseFile($item);
            $this->filename = $tmp['filename'];
            $this->path = $tmp['path'];
        }
        parent::__construct($this->getFullFilename());
    }

    /**
     * Checks if the current object is a directory.
     *
     * @return Boolean true if the current object represents a directory
     *
     */
    public function isDir()
    {
        return self::TYPE_DIRECTORY === $this->type;
    }

    /**
     * Checks if the current object is a file.
     *
     * @return Boolean true if the current object represents a file
     *
     */
    public function isFile()
    {
        return self::TYPE_FILE === $this->type;
    }

    /**
     * Returns the full name of the current object.
     *
     * Example outputs:
     *    /
     *    /pub
     *    /pub/some/dir
     *    /some/file.txt
     *    /other.yml
     *
     * @return string full absolute name of a current object
     *
     */
    public function getFullFilename()
    {
        if ($this->isDir()) {
            return $this->getPath();
        }
        if ($this->path === '/' && $this->filename === '.') {
            return '/';
        }
        if ($this->path === '/') {
            return '/' . $this->filename;
        }

        return $this->path . '/' . $this->filename;
    }

    /**
     * Current object represents a directory.
     * Returns the absolute path of the $item from the current dir.
     *
     * Example:
     *
     * Current dir:
     *     path: /some/dir
     *     name: .
     *
     * Item:
     *    info.xml
     *
     * Result:
     *     /some/dir/info.xml
     *
     * @return string
     *
     */
    public function getItemname($item)
    {
        if ($this->path === '/') {
            return '/' . $item;
        }

        return $this->path . '/' . $item;
    }

    /**
     * Returns filename.
     *
     * @return string
     *
     */
    public function getFilename()
    {
        return $this->filename;
    }

    /**
     * Sets the type.
     *
     * @param integer $type
     */
    public function setType($type)
    {
        $this->type = $type;
    }

    /**
     * Returns the type.
     *
     * @return integer the type of the current object.
     */
    public function getType()
    {
        return $this->type;
    }

    /**
     * Returns path.
     *
     * @return string the path of the current object
     */
    public function getPath()
    {
        return $this->path;
    }

    /**
     * Parses the output of ftp_rawlist to get the type:
     * d (directory), - (file), l (link)
     *
     * @param string $item the output of ftp raw list
     *
     * @return type
     */
    public static function parseRawListItem($item)
    {
        if ($item === '') {
            return self::TYPE_UNKNOWN;
            //throw new \InvalidArgumentException('$item is null!');
        }
        switch ($item[0]) {

            case 'd':
                return self::TYPE_DIRECTORY;

            case '-':
                return self::TYPE_FILE;

            case 'l':
                return self::TYPE_LINK;

            default:
                return self::TYPE_UNKNOWN;
        }
    }

    /**
     * Parse the name of the file into pair: path/name.
     * The name must be absolute (i.e. it must start with /).
     *
     * Example:
     *
     * /abc/defgh/ij.txt
     *
     *     path:     /abc/defgh
     *     filename: ij.txt
     *
     * @param type $file the name to parse
     *
     * @return array
     *
     * @throws \InvalidArgumentException
     */
    public static function parseFile($file = '/')
    {
        if (0 !== strpos($file, '/')) {
            throw new \InvalidArgumentException(sprintf('File must start with /. It doesnt: "%s"', $file));
        }

        if (strlen($file) < 2) {
            throw new \InvalidArgumentException(sprintf('The name must contain at least two characters. It doesnt: "%s"', $file));
        }

        $found = strrpos($file, '/');
        $tmpPath = substr($file, 0, $found);
        $tmpName = substr($file, $found + 1);
        if ($tmpPath === '') {
            $tmpPath = '/';
        }
        return array(
            'filename' => $tmpName,
            'path' => $tmpPath
        );
    }

    /**
     * Returns full filename
     *
     * @return string full filename of the current object
     */
    public function __toString()
    {
        return $this->getFullFilename();
    }

}
