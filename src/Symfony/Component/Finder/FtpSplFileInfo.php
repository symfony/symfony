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
class FtpSplFileInfo extends SplFileInfo
{

    const TYPE_UNKNOWN   = 1;
    const TYPE_DIRECTORY = 2;
    const TYPE_FILE      = 3;
    const TYPE_LINK      = 4;

    private $type     = self::TYPE_FILE;
    private $basePath = '';

    public function __construct($file, $relativePath, $relativePathname, $basePath)
    {
        parent::__construct($file, $relativePath, $relativePathname);
        $this->setBasePath($basePath);
    }

    public function setBasePath($basePath)
    {
        $this->basePath = $basePath;
    }

    public function getBasePath()
    {
        return $this->basePath;
    }

    public function getRealpath()
    {
        return $this->getPathname();
    }

    public function getItemname($item)
    {
        if (0 === strpos($item, '/')) {
            throw new \InvalidArgumentException(sprintf('Item can not be absolute path: "%s".', $item));
        }
        $result = $this->getRealpath();
        $len = strlen($result);
        if ($result[$len - 1] != '/') {
            $result .= '/';
        }

        return $result . $item;
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

}
