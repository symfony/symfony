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

use Symfony\Component\HttpFoundation\SessionStorage\AttributeInterface;
use Symfony\Component\HttpFoundation\FlashBagInterface;

/**
 * Interface for the session.
 *
 * @author Drak <drak@zikula.org>
 */
interface SessionInterface extends AttributeInterface, \Serializable
{
    /**
     * Starts the session storage.
     *
     * @throws \RuntimeException If session fails to start.
     */
    function start();

    /**
     * Invalidates the current session.
     *
     * @return boolean True if session invalidated, false if error.
     */
    function invalidate();

    /**
     * Migrates the current session to a new session id while maintaining all
     * session attributes.
     *
     * @param boolean $destroy Whether to delete the old session or leave it to garbage collection.
     *
     * @return boolean True if session migrated, false if error.
     *
     * @api
     */
    function migrate($destroy = false);

    /**
     * Force the session to be saved and closed.
     *
     * This method is generally not required for real sessions as
     * the session will be automatically saved at the end of
     * code execution.
     */
    function save();

    /**
     * Gets the flashbag interface.
     *
     * @return FlashBagInterface
     */
    function getFlashes();
}
