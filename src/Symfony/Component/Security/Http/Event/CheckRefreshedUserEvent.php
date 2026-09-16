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

use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Http\Firewall\ContextListener;
use Symfony\Contracts\EventDispatcher\Event;

/**
 * Dispatched when a user restored from the session has been reloaded from its
 * user provider, to decide whether the token should be deauthenticated.
 *
 * The verdict of the built-in comparison - password, roles and identifier - is
 * seeded in the event, so a listener can add its own reason to deauthenticate,
 * e.g. an account an administrator disabled during the session, or clear one it
 * does not want to act on.
 *
 * Listeners run on every request of a stateful firewall: keep them cheap and
 * free of side effects.
 *
 * @see ContextListener
 *
 * @author Kevin Bond <kevinbond@gmail.com>
 */
final class CheckRefreshedUserEvent extends Event
{
    private ?AuthenticationException $exception = null;

    public function __construct(
        private TokenInterface $token,
        private UserInterface $originalUser,
        private UserInterface $refreshedUser,
        private bool $userChanged = false,
    ) {
    }

    public function getToken(): TokenInterface
    {
        return $this->token;
    }

    /**
     * Returns the user as it was stored in the session.
     */
    public function getOriginalUser(): UserInterface
    {
        return $this->originalUser;
    }

    /**
     * Returns the user as the user provider just reloaded it.
     */
    public function getRefreshedUser(): UserInterface
    {
        return $this->refreshedUser;
    }

    public function isUserChanged(): bool
    {
        return $this->userChanged;
    }

    /**
     * Deauthenticates the token, or keeps it authenticated despite what a
     * previous listener or the built-in comparison decided.
     *
     * @param AuthenticationException|null $exception The reason to deauthenticate, made available to
     *                                                the listeners of TokenDeauthenticatedEvent
     */
    public function setUserChanged(bool $changed, ?AuthenticationException $exception = null): void
    {
        $this->userChanged = $changed;
        $this->exception = $changed ? $exception ?? $this->exception : null;
    }

    public function getException(): ?AuthenticationException
    {
        return $this->exception;
    }
}
