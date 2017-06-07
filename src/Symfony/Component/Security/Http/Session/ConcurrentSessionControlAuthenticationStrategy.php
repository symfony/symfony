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

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\MaxSessionsExceededException;

/**
 * Strategy which handles concurrent session-control.
 *
 * When invoked following an authentication, it will check whether the user in
 * question should be allowed to proceed, by comparing the number of sessions
 * they already have active with the configured maximumSessions value.
 * The SessionRegistry is used as the source of data on authenticated users and
 * session data.
 *
 * @author Stefan Paschke <stefan.paschke@gmail.com>
 * @author Antonio J. García Lagar <aj@garcialagar.es>
 */
class ConcurrentSessionControlAuthenticationStrategy implements SessionAuthenticationStrategyInterface
{
    protected $registry;
    protected $errorIfMaximumExceeded;
    protected $maximumSessions;

    public function __construct(SessionRegistry $registry, $maximumSessions, $errorIfMaximumExceeded = true)
    {
        $this->registry = $registry;
        $this->setMaximumSessions($maximumSessions);
        $this->setErrorIfMaximumExceeded($errorIfMaximumExceeded);
    }

    /**
     * {@inheritdoc}
     */
    public function onAuthentication(Request $request, TokenInterface $token)
    {
        $username = $token->getUsername();

        $sessions = $this->registry->getAllSessionsInformation($username);
        $sessionCount = count($sessions);
        $maxSessions = $this->getMaximumSessionsForThisUser($username);

        if ($sessionCount < $maxSessions) {
            return;
        }

        if ($sessionCount == $maxSessions) {
            foreach ($sessions as $sessionInfo) {
                /* @var $sessionInfo SessionInformation */
                if ($sessionInfo->getSessionId() == $request->getSession()->getId()) {
                    return;
                }
            }
        }

        $this->allowedSessionsExceeded($sessions, $maxSessions);
    }

    /**
     * Sets a boolean flag that causes a RuntimeException to be thrown if the number of sessions is exceeded.
     *
     * @param bool $errorIfMaximumExceeded
     */
    public function setErrorIfMaximumExceeded($errorIfMaximumExceeded)
    {
        $this->errorIfMaximumExceeded = (bool) $errorIfMaximumExceeded;
    }

    /**
     * Sets the maxSessions property.
     *
     * @param $maximumSessions
     */
    public function setMaximumSessions($maximumSessions)
    {
        $this->maximumSessions = (int) $maximumSessions;
    }

    /**
     * Allows subclasses to customize behavior when too many sessions are detected.
     *
     * @param array $orderedSessions   Array of SessionInformation ordered from
     *                                 newest to oldest
     * @param int   $allowableSessions
     */
    protected function allowedSessionsExceeded($orderedSessions, $allowableSessions)
    {
        if ($this->errorIfMaximumExceeded) {
            throw new MaxSessionsExceededException(sprintf('Maximum number of sessions (%s) exceeded', $allowableSessions));
        }

        // Expire oldest session
        $orderedSessionsVector = array_values($orderedSessions);
        for ($i = $allowableSessions - 1, $countSessions = count($orderedSessionsVector); $i < $countSessions; $i++) {
            $this->registry->expireNow($orderedSessionsVector[$i]->getSessionId());
        }
    }

    /**
     * Method intended for use by subclasses to override the maximum number of sessions that are permitted for a particular authentication.
     *
     * @param  string $username
     * @return int
     */
    protected function getMaximumSessionsForThisUser($username)
    {
        return $this->maximumSessions;
    }
}
