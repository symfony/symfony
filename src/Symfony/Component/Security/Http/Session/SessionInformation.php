<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
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
 */
class SessionInformation
{
    protected $sessionId;
    protected $username;
    protected $expired;
    protected $lastRequest;

    public function __construct($sessionId, $username)
    {
        $this->sessionId = $sessionId;
        $this->username = $username;
    }

    /**
     * Sets the session informations expired date to the current date and time.
     *
     * @return void
     */
    public function expireNow()
    {
        $this->expired = time();
    }

    /**
     * Obtain the last request date.
     *
     * @return integer UNIX Timestamp of the last request date and time.
     */
    public function getLastRequest()
    {
        return $this->lastRequest;
    }

    /**
     * Obtains the username.
     *
     * @return string
     */
    public function getUsername()
    {
        return $this->username;
    }

    /**
     * Obtain the session identifier.
     *
     * @return string $sessionId the session identifier key.
     */
    public function getSessionId()
    {
        return $this->sessionId;
    }

    /**
     * Return wether this session is expired.
     *
     * @return boolean
     */
    public function isExpired()
    {
        return $this->expired && $this->expired < time();
    }

    /**
     * Set the last request date to the current date and time.
     *
     * @return void
     */
    public function refreshLastRequest()
    {
        $this->lastRequest = time();
    }
}
