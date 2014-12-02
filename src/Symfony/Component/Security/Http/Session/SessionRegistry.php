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

use Symfony\Component\HttpFoundation\Session\SessionInterface;

/**
 * SessionRegistry.
 *
 * Stores a registry of SessionInformation instances.
 *
 * @author Stefan Paschke <stefan.paschke@gmail.com>
 * @author Antonio J. García Lagar <aj@garcialagar.es>
 */
class SessionRegistry
{
    private $sessionRegistryStorage;

    public function __construct(SessionRegistryStorageInterface $sessionRegistryStorage)
    {
        $this->sessionRegistryStorage = $sessionRegistryStorage;
    }

    /**
     * Registers a new session for the given user.
     *
     * @param string           $username the given user.
     * @param SessionInterface $session  the session.
     */
    public function registerNewSession($username, SessionInterface $session)
    {
        $sessionInformation = new SessionInformation($username, $session->getId(), $session->getMetadataBag()->getLastUsed());
        $this->setSessionInformation($sessionInformation);
    }

    /**
     * Registers session information for the given user.
     *
     * @param string $username  the given user.
     * @param string $sessionId the session identifier key.
     * @param int    $lastUsed
     */
    public function registerNewSessionInformation($username, $sessionId, $lastUsed = null)
    {
        $lastUsed = $lastUsed ?: time();
        $sessionInformation = new SessionInformation($username, $sessionId, $lastUsed);
        $this->setSessionInformation($sessionInformation);
    }

    /**
     * Returns all the sessions stored for the given user ordered from
     *  MRU (most recently used) to LRU (least recently used).
     *
     * @param  string               $username               the given user.
     * @param  bool                 $includeExpiredSessions
     * @return SessionInformation[] An array of SessionInformation objects.
     */
    public function getAllSessionsInformation($username, $includeExpiredSessions = false)
    {
        return $this->sessionRegistryStorage->getAllSessionsInformation($username, $includeExpiredSessions);
    }

    /**
     * Obtains the session information for the given sessionId.
     *
     * @param  string                  $sessionId the session identifier key.
     * @return SessionInformation|null $sessionInformation
     */
    public function getSessionInformation($sessionId)
    {
        return $this->sessionRegistryStorage->getSessionInformation($sessionId);
    }

    /**
     * Updates the last used timestamp for the given session Id.
     *
     * @param string $sessionId the session identifier key.
     * @parma int $lastUsed if null, current timestamp will be set.
     */
    public function updateLastUsed($sessionId, $lastUsed = null)
    {
        if ($sessionInformation = $this->getSessionInformation($sessionId)) {
            $lastUsed = $lastUsed ?: time();
            //prevents too many trips to session storage
            if ($lastUsed != $sessionInformation->getLastUsed()) {
                $sessionInformation->updateLastUsed($lastUsed);
                $this->setSessionInformation($sessionInformation);
            }
        }
    }

    /**
     * Expires the given sessionId at the give timestamp.
     *
     * @param string $sessionId the session identifier key.
     * @parma int $expired the expiration timestamp.
     */
    public function expireAt($sessionId, $expired)
    {
        if ($sessionInformation = $this->getSessionInformation($sessionId)) {
            $sessionInformation->expireAt($expired);
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
        $this->expireAt($sessionId, time());
    }

    /**
     * Deletes the stored information of one session.
     *
     * @param string $sessionId the session identifier key.
     */
    public function removeSessionInformation($sessionId)
    {
        $this->sessionRegistryStorage->removeSessionInformation($sessionId);
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
}
