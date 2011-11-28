<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HttpFoundation;

use Symfony\Component\HttpFoundation\FlashBagInterface;
use Symfony\Component\HttpFoundation\SessionStorage\AttributeInterface;

/**
 * Interface for the session.
 */
interface SessionInterface extends AttributeInterface, \Serializable
{
    /**
     * Starts the session storage.
     *
     * @api
     */
    function start();

    /**
     * Invalidates the current session.
     *
     * @api
     */
    function invalidate();

    /**
     * Migrates the current session to a new session id while maintaining all
     * session attributes.
     *
     * @api
     */
    function migrate();

    /**
     * Gets the flash messages driver.
     *
     * @return FlashBagInterface
     *
     * @api
     */
    function getFlashBag();
}
