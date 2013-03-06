<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\CssSelector\Token;

/**
 * CSS selector reader.
 *
 * This component is a port of the Python lxml library,
 * which is copyright Infrae and distributed under the BSD license.
 *
 * @author Jean-François Simon <jeanfrancois.simon@sensiolabs.com>
 */
class Reader
{
    private $source;
    private $length;
    private $position;

    public function __construct($source)
    {
        $this->source = $source;
        $this->length = strlen($source);
        $this->position = 0;
    }

    public function isEOF()
    {
        return $this->position > $this->length;
    }

    public function getPosition()
    {
        return $this->position;
    }

    public function getRemainingLength()
    {
        return $this->length - $this->position;
    }

    public function getSubstring($length, $offset = 0)
    {
        return substr($this->source, $this->position + $offset, $length);
    }

    public function getOffset($string)
    {
        return strpos($this->source, $string, $this->position) - $this->position;
    }

    public function findPattern($pattern)
    {
        $source = substr($this->source, $this->position);

        if (preg_match($pattern, $source, $matches)) {
            return $matches;
        }

        return false;
    }

    public function moveForward($length)
    {
        $this->position += $length;
    }

    public function moveToEnd()
    {
        $this->position = $this->length;
    }
}
