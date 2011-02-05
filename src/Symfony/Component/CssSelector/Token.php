<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\CssSelector;

/**
 * Token represents a CSS Selector token.
 *
 * This component is a port of the Python lxml library,
 * which is copyright Infrae and distributed under the BSD license.
 *
 * @author Fabien Potencier <fabien.potencier@symfony-project.com>
 */
class Token
{
    protected $type;
    protected $value;
    protected $position;

    /**
     * Constructor.
     *
     * @param string $type     The type of this token.
     * @param mixed  $value    The value of this token.
     * @param int    $position The order of this token.
     */
    public function __construct($type, $value, $position)
    {
        $this->type = $type;
        $this->value = $value;
        $this->position = $position;
    }

    /**
     * Get a string representation of this token.
     *
     * @return string
     */
    public function __toString()
    {
        return (string) $this->value;
    }

    /**
     * Answers whether this token's type equals to $type.
     *
     * @param  string $type The type to test against this token's one.
     *
     * @return bool
     */
    public function isType($type)
    {
        return $this->type == $type;
    }

    /**
     * Get the position of this token.
     *
     * @return int
     */
    public function getPosition()
    {
        return $this->position;
    }
}
