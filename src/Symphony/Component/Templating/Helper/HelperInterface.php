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
 * HelperInterface is the interface all helpers must implement.
 *
 * @author Fabien Potencier <fabien@symphony.com>
 */
interface HelperInterface
{
    /**
     * Returns the canonical name of this helper.
     *
     * @return string The canonical name
     */
    public function getName();

    /**
     * Sets the default charset.
     *
     * @param string $charset The charset
     */
    public function setCharset($charset);

    /**
     * Gets the default charset.
     *
     * @return string The default charset
     */
    public function getCharset();
}
