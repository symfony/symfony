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
 * SessionRegistry.
 *
 * Maintains a registry of SessionInformation instances.
 *
 * @author Stefan Paschke <stefan.paschke@gmail.com>
 * @author Antonio J. García Lagar <aj@garcialagar.es>
 */
class SessionRegistry
{
    protected $sessionRegistryStorage;

    public function __construct(SessionRegistryStorageInterface $sessionRegistryStorage)
    {
        $this->sessionRegistryStorage = $sessionRegistryStorage;
    }

    /**
     * Obtains all the known sessions for the specified user.
     *
     * @param  string  $username               the specified user.
     * @param  bool    $includeExpiredSessions
     * @return array   An array of SessionInformation objects.
     */
    public function getAllSessions($username, $includeExpiredSessions = false)
    {
        return $this->sessionRegistryStorage->getSessionInformations($username, $includeExpiredSessions);
    }

    /**
     * Obtains the session information for the specified sessionId.
     *
     * @param  string             $sessionId the session identifier key.
     * @return SessionInformation $sessionInformation
     */
    public function getSessionInformation($sessionId)
    {
        return $this->sessionRegistryStorage->getSessionInformation($sessionId);
    }

    /**
     * Sets a SessionInformation object.
     *
     * @param SessionInformation $sessionInformation
     */
    private function setSessionInformation(SessionInformation $sessionInformation)
    {
        $this->sessionRegistryStorage->setSessionInformation($sessionInformation);
    }

    /**
     * Updates the given sessionId so its last request time is equal to the present date and time.
     *
     * @param string $sessionId the session identifier key.
     */
    public function refreshLastRequest($sessionId)
    {
        if ($sessionInformation = $this->getSessionInformation($sessionId)) {
            $sessionInformation->refreshLastRequest();
            $this->setSessionInformation($sessionInformation);
        }
    }

    /**
     * Expires the given sessionId.
     *
     * @param string $sessionId the session identifier key.
     */
    public function expireNow($sessionId)
    {
        if ($sessionInformation = $this->getSessionInformation($sessionId)) {
            $sessionInformation->expireNow();
            $this->setSessionInformation($sessionInformation);
        }
    }

    /**
     * Registers a new session for the specified user.
     *
     * @param string    $sessionId   the session identifier key.
     * @param string    $username    the specified user.
     * @param \DateTime $lastRequest
     */
    public function registerNewSession($sessionId, $username, \DateTime $lastRequest = null)
    {
        $lastRequest = ($lastRequest) ?: new \DateTime();
        $sessionInformation = new SessionInformation($sessionId, $username, $lastRequest);

        $this->setSessionInformation($sessionInformation);
    }

    /**
     * Deletes the maintained information of one session.
     *
     * @param string $sessionId the session identifier key.
     */
    public function removeSessionInformation($sessionId)
    {
        $this->sessionRegistryStorage->removeSessionInformation($sessionId);
    }
}
