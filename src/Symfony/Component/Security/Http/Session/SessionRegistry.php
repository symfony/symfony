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

use Symfony\Component\Security\Core\User\UserInterface;

/**
 * SessionRegistry.
 *
 * Maintains a registry of SessionInformation instances.
 *
 * @author Stefan Paschke <stefan.paschke@gmail.com>
 */
class SessionRegistry
{
    protected $sessionRegistryStorage;
    protected $sessionInformationClass;

    public function __construct(SessionRegistryStorageInterface $sessionRegistryStorage, $sessionInformationClass)
    {
        $this->sessionRegistryStorage = $sessionRegistryStorage;
        $this->sessionInformationClass = $sessionInformationClass;
    }

    /**
     * Obtains all the users for which session information is stored.
     *
     * @return array An array of UserInterface objects.
     */
    public function getAllUsers()
    {
        return $this->sessionRegistryStorage->getUsers();
    }

    /**
     * Obtains all the known sessions for the specified user.
     *
     * @param UserInterface $user the specified user.
     * @param boolean $includeExpiredSessions.
     * @return array An array of SessionInformation objects.
     */
    public function getAllSessions(UserInterface $user, $includeExpiredSessions = false)
    {
        return $this->sessionRegistryStorage->getSessionInformations($user->getUsername(), $includeExpiredSessions);
    }

    /**
     * Obtains the session information for the specified sessionId.
     *
     * @param string $sessionId the session identifier key.
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
     * @return void
     */
    public function setSessionInformation(SessionInformation $sessionInformation)
    {
        $this->sessionRegistryStorage->setSessionInformation($sessionInformation);
    }

    /**
     * Updates the given sessionId so its last request time is equal to the present date and time.
     *
     * @param string $sessionId the session identifier key.
     * @return void
     */
    public function refreshLastRequest($sessionId)
    {
        if ($sessionInformation = $this->getSessionInformation($sessionId)) {
            $sessionInformation->refreshLastRequest();
            $this->sessionRegistryStorage->setSessionInformation($sessionInformation);
        }
    }

    /**
     * Registers a new session for the specified user.
     *
     * @param string $sessionId the session identifier key.
     * @param UserInterface $user the specified user.
     * @return void
     */
    public function registerNewSession($sessionId, UserInterface $user)
    {
        $sessionInformation = new $this->sessionInformationClass($sessionId, $user->getUsername());
        $sessionInformation->refreshLastRequest();

        $this->sessionRegistryStorage->setSessionInformation($sessionInformation);
    }

    /**
     * Deletes all the session information being maintained for the specified sessionId.
     *
     * @param string $sessionId the session identifier key.
     * @param UserInterface $user the specified user.
     * @return void
     */
    public function removeSessionInformation($sessionId)
    {
        $this->sessionRegistryStorage->removeSessionInformation($sessionId);
    }
}
