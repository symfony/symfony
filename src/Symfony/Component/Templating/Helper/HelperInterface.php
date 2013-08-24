<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Templating\Helper;

/**
 * HelperInterface is the interface all helpers must implement.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 *
 * @api
 */
interface HelperInterface
{
    /**
     * Returns the canonical name of this helper.
     *
     * @return string The canonical name
     *
     * @api
     *
     * @since v2.1.0
     */
    public function getName();

    /**
     * Sets the default charset.
     *
     * @param string $charset The charset
     *
     * @api
     *
     * @since v2.1.0
     */
    public function setCharset($charset);

    /**
     * Gets the default charset.
     *
     * @return string The default charset
     *
     * @api
     *
     * @since v2.1.0
     */
    public function getCharset();
}
