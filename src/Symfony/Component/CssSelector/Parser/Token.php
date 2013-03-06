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
 * CSS selector token.
 *
 * This component is a port of the Python lxml library,
 * which is copyright Infrae and distributed under the BSD license.
 *
 * @author Jean-François Simon <jeanfrancois.simon@sensiolabs.com>
 */
class Token
{
    const TYPE_FILE_END   = 0;
    const TYPE_DELIMITER  = 1;
    const TYPE_WHITESPACE = 2;
    const TYPE_IDENTIFIER = 3;
    const TYPE_HASH       = 4;
    const TYPE_NUMBER     = 5;
    const TYPE_STRING     = 6;

    const VALUE_WILDCARD = '*';

    /**
     * @var int
     */
    private $type;

    /**
     * @var string
     */
    private $value;

    /**
     * @var int
     */
    private $position;

    /**
     * @param int    $type
     * @param string $value
     * @param int    $position
     */
    public function __construct($type, $value, $position)
    {
        $this->type = $type;
        $this->value = $value;
        $this->position = $position;
    }

    /**
     * @return int
     */
    public function getType()
    {
        return $this->type;
    }

    /**
     * @return string
     */
    public function getValue()
    {
        return $this->value;
    }

    /**
     * @return int
     */
    public function getPosition()
    {
        return $this->position;
    }

    /**
     * @return boolean
     */
    public function isFileEnd()
    {
        return self::TYPE_FILE_END === $this->type;
    }

    /**
     * @return boolean
     */
    public function isDelimiter()
    {
        return self::TYPE_DELIMITER === $this->type;
    }

    /**
     * @return boolean
     */
    public function isWildcardDelimiter()
    {
        return self::TYPE_DELIMITER === $this->type && self::VALUE_WILDCARD === $this->value;
    }

    /**
     * @return boolean
     */
    public function isWhitespace()
    {
        return self::TYPE_WHITESPACE === $this->type;
    }

    /**
     * @return boolean
     */
    public function isIdentifier()
    {
        return self::TYPE_IDENTIFIER === $this->type;
    }

    /**
     * @return boolean
     */
    public function isHash()
    {
        return self::TYPE_HASH === $this->type;
    }

    /**
     * @return boolean
     */
    public function isNumber()
    {
        return self::TYPE_NUMBER === $this->type;
    }

    /**
     * @return boolean
     */
    public function isString()
    {
        return self::TYPE_STRING === $this->type;
    }
}
