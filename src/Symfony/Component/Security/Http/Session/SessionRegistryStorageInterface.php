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
 * SessionRegistryStorageInterface.
 *
 * Stores the SessionInformation instances maintained in the SessionRegistry.
 *
 * @author Stefan Paschke <stefan.paschke@gmail.com>
 * @author Antonio J. García Lagar <aj@garcialagar.es>
 */
interface SessionRegistryStorageInterface
{
    /**
     * Obtains the session information for the specified sessionId.
     *
     * @param  string                  $sessionId the session identifier key.
     * @return SessionInformation|null $sessionInformation
     */
    public function getSessionInformation($sessionId);

    /**
     * Obtains the maintained information for one user ordered from newest to
     *  oldest
     *
     * @param  string               $username               The user identifier.
     * @param  bool                 $includeExpiredSessions
     * @return SessionInformation[] An array of SessionInformation objects.
     */
    public function getSessionInformations($username, $includeExpiredSessions = false);

    /**
     * Sets a SessionInformation object.
     *
     * @param SessionInformation $sessionInformation
     */
    public function setSessionInformation(SessionInformation $sessionInformation);

    /**
     * Deletes the maintained information of one session.
     *
     * @param string $sessionId the session identifier key.
     */
    public function removeSessionInformation($sessionId);
}
