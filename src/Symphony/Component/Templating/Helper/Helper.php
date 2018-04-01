<?php

/*
 * This file is part of the Symphony package.
 *
 * (c) Fabien Potencier <fabien@symphony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symphony\Component\Templating\Helper;

/**
 * Helper is the base class for all helper classes.
 *
 * Most of the time, a Helper is an adapter around an existing
 * class that exposes a read-only interface for templates.
 *
 * @author Fabien Potencier <fabien@symphony.com>
 */
abstract class Helper implements HelperInterface
{
    protected $charset = 'UTF-8';

    /**
     * Sets the default charset.
     *
     * @param string $charset The charset
     */
    public function setCharset($charset)
    {
        $this->charset = $charset;
    }

    /**
     * Gets the default charset.
     *
     * @return string The default charset
     */
    public function getCharset()
    {
        return $this->charset;
    }
}
