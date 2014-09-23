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
 * SessionRegistryStorageInterface.
 *
 * Stores the SessionInformation instances maintained in the SessionRegistry.
 *
 * @author Stefan Paschke <stefan.paschke@gmail.com>
 */
interface SessionRegistryStorageInterface
{
    /**
     * Obtains all the users for which session information is stored.
     *
     * @return array An array of UserInterface objects.
     */
    function getUsers();

    /**
     * Obtains the session information for the specified sessionId.
     *
     * @param string $sessionId the session identifier key.
     * @return SessionInformation $sessionInformation
     */
    function getSessionInformation($sessionId);

    /**
     * Obtains the maintained information for one user.
     *
     * @param string $username The user identifier.
     * @param boolean $includeExpiredSessions.
     * @return array An array of SessionInformation objects.
     */
    function getSessionInformations($username, $includeExpiredSessions);

    /**
     * Sets a SessionInformation object.
     *
     * @param SessionInformation $sessionInformation
     */
    function setSessionInformation(SessionInformation $sessionInformation);

    /**
     * Deletes the maintained information of one session.
     *
     * @param string $sessionId the session identifier key.
     */
    function removeSessionInformation($sessionId);
}
