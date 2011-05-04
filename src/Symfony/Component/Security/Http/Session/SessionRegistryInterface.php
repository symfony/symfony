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
 * SessionRegistryInterface.
 *
 * The SessionRegistry is used as the source of data on authenticated users and session data.
 *
 * @author Stefan Paschke <stefan.paschke@gmail.com>
 */
interface SessionRegistryInterface
{
    /**
     * Obtains all the known users in the SessionRegistry.
     */
    function getAllUsers();

    /**
     * Obtains all the known sessions for the specified user.
     */
    function getAllSessions($user, $includeExpiredSessions = false);

    /**
     * Obtains the session information for the specified sessionId.
     */
    function getSessionInformation($sessionId);

    /**
     * Updates the given sessionId so its last request time is equal to the present date and time.
     */
    function refreshLastRequest($sessionId);

    /**
     * Registers a new session for the specified principal.
     */
    function registerNewSession($sessionId, $user);

    /**
     * Deletes all the session information being maintained for the specified sessionId.
     */
    function removeSessionInformation($sessionId, $user);
}
