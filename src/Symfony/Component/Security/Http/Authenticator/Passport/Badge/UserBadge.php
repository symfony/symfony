<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Security\Http\Authenticator\Passport\Badge;

use Symfony\Component\Security\Core\Exception\UsernameNotFoundException;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Http\EventListener\UserProviderListener;

/**
 * Represents the user in the authentication process.
 *
 * It uses an identifier (e.g. email, or username) and
 * "user loader" to load the related User object.
 *
 * @author Wouter de Jong <wouter@wouterj.nl>
 *
 * @experimental in 5.2
 */
class UserBadge implements BadgeInterface
{
    private $userIdentifier;
    private $userLoader;
    private $user;

    /**
     * Initializes the user badge.
     *
     * You must provide a $userIdentifier. This is a unique string representing the
     * user for this authentication (e.g. the email if authentication is done using
     * email + password; or a string combining email+company if authentication is done
     * based on email *and* company name). This string can be used for e.g. login throttling.
     *
     * Optionally, you may pass a user loader. This callable receives the $userIdentifier
     * as argument and must return a UserInterface object (otherwise a UsernameNotFoundException
     * is thrown). If this is not set, the default user provider will be used with
     * $userIdentifier as username.
     */
    public function __construct(string $userIdentifier, ?callable $userLoader = null)
    {
        $this->userIdentifier = $userIdentifier;
        $this->userLoader = $userLoader;
    }

    public function getUserIdentifier(): string
    {
        return $this->userIdentifier;
    }

    public function getUser(): UserInterface
    {
        if (null === $this->user) {
            if (null === $this->userLoader) {
                throw new \LogicException(sprintf('No user loader is configured, did you forget to register the "%s" listener?', UserProviderListener::class));
            }

            $this->user = ($this->userLoader)($this->userIdentifier);
            if (!$this->user instanceof UserInterface) {
                throw new UsernameNotFoundException();
            }
        }

        return $this->user;
    }

    public function getUserLoader(): ?callable
    {
        return $this->userLoader;
    }

    public function setUserLoader(callable $userLoader): void
    {
        $this->userLoader = $userLoader;
    }

    public function isResolved(): bool
    {
        return true;
    }
}
