<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HttpFoundation\Session;

use Symfony\Component\HttpFoundation\Session\Storage\MetadataBag;

/**
 * Interface for the session.
 *
 * @author Drak <drak@zikula.org>
 */
interface SessionInterface
{
    /**
     * Starts the session storage.
     *
     * @return bool True if session started
     *
     * @throws \RuntimeException if session fails to start
     */
    public function start(): bool;

    /**
     * Returns the session ID.
     *
     * @return string The session ID
     */
    public function getId(): string;

    /**
     * Sets the session ID.
     *
     * @param string $id
     */
    public function setId(string $id): void;

    /**
     * Returns the session name.
     *
     * @return mixed The session name
     */
    public function getName();

    /**
     * Sets the session name.
     *
     * @param string $name
     */
    public function setName(string $name): void;

    /**
     * Invalidates the current session.
     *
     * Clears all session attributes and flashes and regenerates the
     * session and deletes the old session from persistence.
     *
     * @param int $lifetime Sets the cookie lifetime for the session cookie. A null value
     *                      will leave the system settings unchanged, 0 sets the cookie
     *                      to expire with browser session. Time is in seconds, and is
     *                      not a Unix timestamp.
     *
     * @return bool True if session invalidated, false if error
     */
    public function invalidate(int $lifetime = null): bool;

    /**
     * Migrates the current session to a new session id while maintaining all
     * session attributes.
     *
     * @param bool $destroy  Whether to delete the old session or leave it to garbage collection
     * @param int  $lifetime Sets the cookie lifetime for the session cookie. A null value
     *                       will leave the system settings unchanged, 0 sets the cookie
     *                       to expire with browser session. Time is in seconds, and is
     *                       not a Unix timestamp.
     *
     * @return bool True if session migrated, false if error
     */
    public function migrate(bool $destroy = false, int $lifetime = null): bool;

    /**
     * Force the session to be saved and closed.
     *
     * This method is generally not required for real sessions as
     * the session will be automatically saved at the end of
     * code execution.
     */
    public function save(): void;

    /**
     * Checks if an attribute is defined.
     *
     * @param string $name The attribute name
     *
     * @return bool true if the attribute is defined, false otherwise
     */
    public function has(string $name): bool;

    /**
     * Returns an attribute.
     *
     * @param string $name    The attribute name
     * @param mixed  $default The default value if not found
     *
     * @return mixed
     */
    public function get(string $name, $default = null);

    /**
     * Sets an attribute.
     *
     * @param string $name
     * @param mixed  $value
     */
    public function set(string $name, $value): void;

    /**
     * Returns attributes.
     *
     * @return array Attributes
     */
    public function all(): array;

    /**
     * Sets attributes.
     *
     * @param array $attributes Attributes
     */
    public function replace(array $attributes): void;

    /**
     * Removes an attribute.
     *
     * @param string $name
     *
     * @return mixed The removed value or null when it does not exist
     */
    public function remove(string $name);

    /**
     * Clears all attributes.
     */
    public function clear(): void;

    /**
     * Checks if the session was started.
     *
     * @return bool
     */
    public function isStarted(): bool;

    /**
     * Registers a SessionBagInterface with the session.
     */
    public function registerBag(SessionBagInterface $bag): void;

    /**
     * Gets a bag instance by name.
     *
     * @param string $name
     *
     * @return SessionBagInterface
     */
    public function getBag(string $name): SessionBagInterface;

    /**
     * Gets session meta.
     *
     * @return MetadataBag
     */
    public function getMetadataBag(): MetadataBag;
}
