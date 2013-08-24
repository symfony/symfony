<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\CssSelector\Parser;

/**
 * CSS selector reader.
 *
 * This component is a port of the Python cssselector library,
 * which is copyright Ian Bicking, @see https://github.com/SimonSapin/cssselect.
 *
 * @author Jean-François Simon <jeanfrancois.simon@sensiolabs.com>
 *
 * @since v2.3.0
 */
class Reader
{
    /**
     * @var string
     */
    private $source;

    /**
     * @var int
     */
    private $length;

    /**
     * @var int
     */
    private $position;

    /**
     * @param string $source
     *
     * @since v2.3.0
     */
    public function __construct($source)
    {
        $this->source = $source;
        $this->length = strlen($source);
        $this->position = 0;
    }

    /**
     * @return bool
     *
     * @since v2.3.0
     */
    public function isEOF()
    {
        return $this->position >= $this->length;
    }

    /**
     * @return int
     *
     * @since v2.3.0
     */
    public function getPosition()
    {
        return $this->position;
    }

    /**
     * @return int
     *
     * @since v2.3.0
     */
    public function getRemainingLength()
    {
        return $this->length - $this->position;
    }

    /**
     * @param int $length
     * @param int $offset
     *
     * @return string
     *
     * @since v2.3.0
     */
    public function getSubstring($length, $offset = 0)
    {
        return substr($this->source, $this->position + $offset, $length);
    }

    /**
     * @param string $string
     *
     * @return int
     *
     * @since v2.3.0
     */
    public function getOffset($string)
    {
        $position = strpos($this->source, $string, $this->position);

        return false === $position ? false : $position - $this->position;
    }

    /**
     * @param string $pattern
     *
     * @return bool
     *
     * @since v2.3.0
     */
    public function findPattern($pattern)
    {
        $source = substr($this->source, $this->position);

        if (preg_match($pattern, $source, $matches)) {
            return $matches;
        }

        return false;
    }

    /**
     * @param int $length
     *
     * @since v2.3.0
     */
    public function moveForward($length)
    {
        $this->position += $length;
    }

    /**
     *
     * @since v2.3.0
     */
    public function moveToEnd()
    {
        $this->position = $this->length;
    }
}
