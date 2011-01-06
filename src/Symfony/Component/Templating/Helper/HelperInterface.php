<?php

namespace Symfony\Component\Templating\Helper;

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

/**
 * HelperInterface is the interface all helpers must implement.
 *
 * @author Fabien Potencier <fabien.potencier@symfony-project.com>
 */
interface HelperInterface
{
    /**
     * Returns the canonical name of this helper.
     *
     * @return string The canonical name
     */
    function getName();

    /**
     * Sets the default charset.
     *
     * @param string $charset The charset
     */
    function setCharset($charset);

    /**
     * Gets the default charset.
     *
     * @return string The default charset
     */
    function getCharset();
}
