<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Security\Http\Session;

/**
 * SessionInformation.
 *
 * Represents a record of a session. This is primarily used for concurrent session support.
 *
 * @author Stefan Paschke <stefan.paschke@gmail.com>
 * @author Antonio J. García Lagar <aj@garcialagar.es>
 */
class SessionInformation
{
    private $username;
    private $sessionId;
    private $expired;
    private $lastUsed;

    public function __construct($username, $sessionId, $lastUsed, $expired = null)
    {
        $this->sessionId = (string) $sessionId;
        $this->username = (string) $username;
        $this->lastUsed = (int) $lastUsed;

        if (null !== $expired) {
            $this->expired = (int) $expired;
        }
    }

    /**
     * Sets the session expiration timestamp.
     */
    public function expireAt($expired)
    {
        $this->expired = (int) $expired;
    }

    /**
     * Set the last used timestamp.
     */
    public function updateLastUsed($lastUsed)
    {
        $this->lastUsed = (int) $lastUsed;
    }

    /**
     * Obtain the last used timestamp.
     *
     * @return int the last request timestamp.
     */
    public function getLastUsed()
    {
        return $this->lastUsed;
    }

    /**
     * Gets the username.
     *
     * @return string
     */
    public function getUsername()
    {
        return $this->username;
    }

    /**
     * Gets the session identifier key.
     *
     * @return string $sessionId the session identifier key.
     */
    public function getSessionId()
    {
        return $this->sessionId;
    }

    /**
     * Return whether this session is expired.
     *
     * @return bool
     */
    public function isExpired()
    {
        return null !== $this->expired && $this->expired < time();
    }
}
