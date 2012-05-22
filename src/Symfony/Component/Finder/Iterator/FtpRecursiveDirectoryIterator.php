<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Finder\Iterator;

use Symfony\Component\Finder\FtpSplFileInfo;
use Symfony\Component\Finder\Iterator\Ftp;

/**
 * RecursiveDirectoryFtpIterator implements \RecursiveDirectoryIterator
 * over ftp stream.
 *
 * @author Włodzimierz Gajda <gajdaw@gajdaw.pl>
 */
class RecursiveDirectoryFtpIterator extends FtpSplFileInfo implements \RecursiveIterator
{

    private $contents = array();
    private $ftp = false;

    /**
     * Constructor.
     *
     * Examples:
     *     $i = new RecursiveDirectoryFtpIterator('ftp://example.com/dir');
     *     $i = new RecursiveDirectoryFtpIterator('/pub/dir', self::TYPE_DIRECTORY);
     *     $i = new RecursiveDirectoryFtpIterator('/pub/dir/info.txt', self::TYPE_FILE);
     *
     *
     * @param type $item
     * @param type $type
     * @throws \InvalidArgumentException
     */
    public function __construct($item, $type = self::TYPE_DIRECTORY)
    {
        if (0 === strpos($item, 'ftp://')) {
            $parsedUrl = parse_url($item);
            if ($parsedUrl['scheme'] != 'ftp') {
                throw new \InvalidArgumentException(sprintf('Ftp url expected. Incorrect value: "%s"', $item));
            }
            $defaults = array(
                'user' => 'anonymous',
                'pass' => '',
                'path' => '/'
            );
            $defaults = array_merge($defaults, $parsedUrl);
            $this->ftp = new Ftp($defaults);
            $this->ftp->connectAndLogin();
            parent::__construct($defaults['path']);
        } else {
            parent::__construct($item, $type);
        }
    }

    public function isConnected()
    {
        return $this->ftp->isConnected();
    }

    public function getConnection()
    {
        return $this->ftp;
    }

    public function setConnection($ftp)
    {
        $this->ftp = $ftp;
    }

    public function getChildren()
    {
        $iterator = new RecursiveDirectoryFtpIterator($this->current()->getPath());
        $iterator->setConnection($this->getConnection());

        return $iterator;
    }

    public function hasChildren()
    {
        return $this->current()->isDir();
    }

    public function current()
    {
        return current($this->contents);
    }

    public function key()
    {
        return $this->current()->getFullfilename();
    }

    public function next()
    {
        next($this->contents);
    }

    public function rewind()
    {
        $this->ftp->chDir($this->getPath());

        $names = $this->ftp->nList($this->getFilename());
        $types = $this->ftp->rawList($this->getFilename());

        $this->contents = array();
        foreach ($names as $k => $name) {
            $parsedType = self::parseRawListItem($types[$k]);
            $iterator = new RecursiveDirectoryFtpIterator($this->getItemname($name), $parsedType);
            $iterator->setConnection($this->getConnection());
            $this->contents[] = $iterator;
        }
    }

    public function valid()
    {
        return $this->isConnected() && (current($this->contents) instanceof FtpSplFileInfo);
    }

    public static function isValidFtpUrl($url)
    {
        if (0 !== strpos($url, 'ftp://')) {
            return false;
        }
        $parsedUrl = parse_url($url);
        if ($parsedUrl['scheme'] === 'ftp') {
            return true;
        }

        return false;
    }

    /**
     * Returns relative path.
     *
     * Current dir: /my/data/
     *
     * (a) /my/data/lorem/ipsum/one/two/three/
     * (b) /my/data/lorem/ipsum/one/two/three/file.txt
     *
     * ->in('lorem/ipsum')
     *
     * For directory "three":
     *     getRelativePath()      for (a) returns one/two
     *     getRelativePathname()  for (a) returns one/two/three
     *
     * For "file.txt":
     *     getRelativePath()      for (b) returns one/two/three
     *     getRelativePathname()  for (b) returns one/two/three/file.txt
     *
     * getRelativePath      <===>  RecursiveDirectoryIterator::getSubPath()
     * getRelativePathname  <===>  RecursiveDirectoryIterator::getSubPathname()
     *
     * @return string
     *
     */
    public function getRelativePath()
    {
        return $this->getPath();
    }

    /**
     * Returns relative pathname.
     *
     * @return string
     *
     */
    public function getRelativePathname()
    {
        return $this->getFullFilename();
    }

}
