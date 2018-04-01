<?php

/*
 * This file is part of the Symphony package.
 *
 * (c) Fabien Potencier <fabien@symphony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symphony\Component\Asset\Context;

/**
 * Holds information about the current request.
 *
 * @author Fabien Potencier <fabien@symphony.com>
 */
interface ContextInterface
{
    /**
     * Gets the base path.
     *
     * @return string The base path
     */
    public function getBasePath();

    /**
     * Checks whether the request is secure or not.
     *
     * @return bool true if the request is secure, false otherwise
     */
    public function isSecure();
}
