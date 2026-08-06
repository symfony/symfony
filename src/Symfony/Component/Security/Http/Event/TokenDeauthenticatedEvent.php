<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Security\Http\Event;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Contracts\EventDispatcher\Event;

/**
 * This event is dispatched when the current security token is deauthenticated
 * when trying to reference the token.
 *
 * This includes changes in the user ({@see DeauthenticatedEvent}), but
 * also cases where there is no user provider available to refresh the user.
 *
 * Use this event if you want to trigger some actions whenever a user is
 * deauthenticated and redirected back to the authentication entry point
 * (e.g. clearing all remember-me cookies).
 *
 * @author Wouter de Jong <wouter@wouterj.nl>
 */
final class TokenDeauthenticatedEvent extends Event
{
    /**
     * @param string|null        $reason          A human-readable, free-form sentence explaining the deauthentication
     * @param list<class-string> $providerClasses The user providers that contributed to the deauthentication decision
     */
    public function __construct(
        private TokenInterface $originalToken,
        private Request $request,
        private ?string $reason = null,
        private array $providerClasses = [],
    ) {
    }

    public function getOriginalToken(): TokenInterface
    {
        return $this->originalToken;
    }

    public function getRequest(): Request
    {
        return $this->request;
    }

    /**
     * Returns a human-readable, free-form sentence explaining why the token
     * was deauthenticated, e.g. for display in the profiler.
     */
    public function getReason(): ?string
    {
        return $this->reason;
    }

    /**
     * Returns the classes of the user providers that contributed to the
     * deauthentication decision: the providers that detected a change in the
     * user, or the ones that could not find the user.
     *
     * @return list<class-string>
     */
    public function getProviderClasses(): array
    {
        return $this->providerClasses;
    }
}
