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
 */
class SessionInformation
{
    private $sessionId;
    private $username;
    private $expired;
    private $lastRequest;

    public function __construct($sessionId, $username, \DateTime $lastRequest, \DateTime $expired = null)
    {
        $this->setSessionId($sessionId);
        $this->setUsername($username);
        $this->setLastRequest($lastRequest);

        if (null !== $expired) {
            $this->setExpired($expired);
        }
    }

    /**
     * Sets the session informations expiration date to the current date and time.
     *
     */
    public function expireNow()
    {
        $this->setExpired(new \DateTime());
    }

    /**
     * Obtain the last request date.
     *
     * @return DateTime the last request date and time.
     */
    public function getLastRequest()
    {
        return $this->lastRequest;
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
        return null !== $this->getExpired() && $this->getExpired()->getTimestamp() < microtime(true);
    }

    /**
     * Set the last request date to the current date and time.
     *
     */
    public function refreshLastRequest()
    {
        $this->lastRequest = new \DateTime();
    }

    private function getExpired()
    {
        return $this->expired;
    }

    private function setExpired(\DateTime $expired)
    {
        $this->expired = $expired;
    }

    private function setLastRequest(\DateTime $lastRequest)
    {
        $this->lastRequest = $lastRequest;
    }

    private function setSessionId($sessionId)
    {
        $this->sessionId = $sessionId;
    }

    private function setUsername($username)
    {
        $this->username = $username;
    }
}
