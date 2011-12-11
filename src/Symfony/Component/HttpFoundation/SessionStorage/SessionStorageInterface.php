<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HttpFoundation\SessionStorage;

use Symfony\Component\HttpFoundation\FlashBagInterface;
use Symfony\Component\HttpFoundation\AttributeBagInterface;

/**
 * SessionStorageInterface.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 * @author Drak <drak@zikula.org>
 *
 * @api
 */
interface SessionStorageInterface
{
    /**
     * Starts the session.
     *
     * @throws \RuntimeException If something goes wrong starting the session.
     *
     * @api
     */
    function start();

    /**
     * Returns the session ID
     *
     * @return mixed The session ID or false if the session has not started.
     *
     * @api
     */
    function getId();

    /**
     * Regenerates id that represents this storage.
     *
     * @param  Boolean $destroy Destroy session when regenerating?
     *
     * @return Boolean True if session regenerated, false if error
     *
     * @throws \RuntimeException If an error occurs while regenerating this storage
     *
     * @api
     */
    function regenerate($destroy = false);

    /**
     * Gets the FlashBagInterface driver.
     *
     * @return FlashBagInterface
     */
    function getFlashes();

    /**
     * Gets the AttributeBagInterface driver.
     *
     * @return AttributeBagInterface
     */
    function getAttributes();
}
