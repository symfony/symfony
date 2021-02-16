<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bridge\PsrHttpMessage\Tests\Fixtures;

use Psr\Http\Message\StreamInterface;

/**
 * @author Kévin Dunglas <dunglas@gmail.com>
 */
class Stream implements StreamInterface
{
    private $stringContent;
    private $eof = true;

    public function __construct($stringContent = '')
    {
        $this->stringContent = $stringContent;
    }

    public function __toString()
    {
        return $this->stringContent;
    }

    public function close()
    {
    }

    public function detach()
    {
        return fopen('data://text/plain,'.$this->stringContent, 'r');
    }

    public function getSize()
    {
    }

    public function tell()
    {
        return 0;
    }

    public function eof()
    {
        return $this->eof;
    }

    public function isSeekable()
    {
        return true;
    }

    public function seek($offset, $whence = \SEEK_SET)
    {
    }

    public function rewind()
    {
        $this->eof = false;
    }

    public function isWritable()
    {
        return false;
    }

    public function write($string)
    {
    }

    public function isReadable()
    {
        return true;
    }

    public function read($length)
    {
        $this->eof = true;

        return $this->stringContent;
    }

    public function getContents()
    {
        return $this->stringContent;
    }

    public function getMetadata($key = null)
    {
    }
}
