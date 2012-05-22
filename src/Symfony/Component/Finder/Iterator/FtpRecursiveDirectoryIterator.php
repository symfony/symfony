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
use Symfony\Component\Finder\Util\Ftp;

/**
 * RecursiveDirectoryFtpIterator implements \RecursiveDirectoryIterator
 * over ftp stream.
 *
 * @author Włodzimierz Gajda <gajdaw@gajdaw.pl>
 */
class FtpRecursiveDirectoryIterator extends FtpSplFileInfo implements \RecursiveIterator
{

    private $contents = array();
    private $ftp      = false;

    public function __construct($file)
    {
        if (0 === strpos($file, 'ftp://')) {
            $parsedUrl = parse_url($file);
            if (isset($parsedUrl['scheme']) && $parsedUrl['scheme'] != 'ftp') {
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
            parent::__construct($defaults['path'], '', '', '');
        } else {
            parent::__construct($file, '', '', '');
        }
        $this->setType(self::TYPE_DIRECTORY);
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
        $iterator = new FtpRecursiveDirectoryIterator($this->current()->getRealPath());
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
        return $this->current()->getRealPath();
    }

    public function next()
    {
        next($this->contents);
    }

    public function rewind()
    {
        $this->refresh();
    }

    /**
     *
     * Destroy $this->contents and generate is once again?
     *
     */
    public function refresh()
    {
        $this->ftp->chDir($this->getPathname());

        $names = $this->ftp->nList($this->getPathname());
        $types = $this->ftp->rawList($this->getPathname());

        $this->contents = array();
        foreach ($names as $k => $name) {
            $parsedType = self::parseRawListItem($types[$k]);
            $item = new FtpSplFileInfo($name, '', '', '');
            $item->setType($parsedType);
            $this->contents[] = $item;
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

}