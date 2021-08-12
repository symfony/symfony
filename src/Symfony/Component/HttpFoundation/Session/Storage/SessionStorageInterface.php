<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HttpFoundation\Session\Storage;

use Symfony\Component\HttpFoundation\Session\SessionBagInterface;

/**
 * StorageInterface.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 * @author Drak <drak@zikula.org>
 */
interface SessionStorageInterface
{
    /**
     * Starts the session.
     *
     * @return bool
     *
     * @throws \RuntimeException if something goes wrong starting the session
     */
    public function start();

    /**
     * Checks if the session is started.
     *
     * @return bool
     */
    public function isStarted();

    /**
     * Returns the session ID.
     *
     * @return string
     */
    public function getId();

    /**
     * Sets the session ID.
     */
    public function setId(string $id);

    /**
     * Returns the session name.
     *
     * @return string
     */
    public function getName();

    /**
     * Sets the session name.
     */
    public function setName(string $name);

    /**
     * Regenerates id that represents this storage.
     *
     * This method must invoke session_regenerate_id($destroy) unless
     * this interface is used for a storage object designed for unit
     * or functional testing where a real PHP session would interfere
     * with testing.
     *
     * Note regenerate+destroy should not clear the session data in memory
     * only delete the session data from persistent storage.
     *
     * Care: When regenerating the session ID no locking is involved in PHP's
     * session design. See https://bugs.php.net/61470 for a discussion.
     * So you must make sure the regenerated session is saved BEFORE sending the
     * headers with the new ID. Symfony's HttpKernel offers a listener for this.
     * See Symfony\Component\HttpKernel\EventListener\SaveSessionListener.
     * Otherwise session data could get lost again for concurrent requests with the
     * new ID. One result could be that you get logged out after just logging in.
     *
     * @param bool $destroy  Destroy session when regenerating?
     * @param int  $lifetime Sets the cookie lifetime for the session cookie. A null value
     *                       will leave the system settings unchanged, 0 sets the cookie
     *                       to expire with browser session. Time is in seconds, and is
     *                       not a Unix timestamp.
     *
     * @return bool
     *
     * @throws \RuntimeException If an error occurs while regenerating this storage
     */
    public function regenerate(bool $destroy = false, int $lifetime = null);

    /**
     * Force the session to be saved and closed.
     *
     * This method must invoke session_write_close() unless this interface is
     * used for a storage object design for unit or functional testing where
     * a real PHP session would interfere with testing, in which case
     * it should actually persist the session data if required.
     *
     * @throws \RuntimeException if the session is saved without being started, or if the session
     *                           is already closed
     */
    public function save();

    /**
     * Clear all session data in memory.
     */
    public function clear();

    /**
     * Gets a SessionBagInterface by name.
     *
     * @return SessionBagInterface
     *
     * @throws \InvalidArgumentException If the bag does not exist
     */
    public function getBag(string $name);

    /**
     * Registers a SessionBagInterface for use.
     */
    public function registerBag(SessionBagInterface $bag);

    /**
     * @return MetadataBag
     */
    public function getMetadataBag();
}
