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

use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Core\Exception\AuthenticationServiceException;
use Symfony\Component\Security\Core\Exception\BadCredentialsException;
use Symfony\Component\Security\Core\Exception\UserNotFoundException;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Http\EventListener\UserProviderListener;

/**
 * Represents the user in the authentication process.
 *
 * It uses an identifier (e.g. email, or username) and
 * "user loader" to load the related User object.
 *
 * @author Wouter de Jong <wouter@wouterj.nl>
 */
class UserBadge implements BadgeInterface
{
    public const MAX_USERNAME_LENGTH = 4096;

    /** @var callable|null */
    private $userLoader;
    private UserInterface $user;
    private ?\Closure $identifierNormalizer = null;

    /**
     * Initializes the user badge.
     *
     * You must provide a $userIdentifier. This is a unique string representing the
     * user for this authentication (e.g. the email if authentication is done using
     * email + password; or a string combining email+company if authentication is done
     * based on email *and* company name). This string can be used for e.g. login throttling.
     *
     * Optionally, you may pass a user loader. This callable receives the $userIdentifier
     * as argument and must return a UserInterface object (otherwise an AuthenticationServiceException
     * is thrown). If this is not set, the default user provider will be used with
     * $userIdentifier as username.
     */
    public function __construct(
        private string $userIdentifier,
        ?callable $userLoader = null,
        private ?array $attributes = null,
        ?\Closure $identifierNormalizer = null,
    ) {
        if ('' === $userIdentifier) {
            trigger_deprecation('symfony/security-http', '7.2', 'Using an empty string as user identifier is deprecated and will throw an exception in Symfony 8.0.');
            // throw new BadCredentialsException('Empty user identifier.');
        }

        if (\strlen($userIdentifier) > self::MAX_USERNAME_LENGTH) {
            throw new BadCredentialsException('Username too long.');
        }
        if ($identifierNormalizer) {
            $this->identifierNormalizer = static fn () => $identifierNormalizer($userIdentifier);
        }

        $this->userLoader = $userLoader;
    }

    public function getUserIdentifier(): string
    {
        if (isset($this->identifierNormalizer)) {
            $this->userIdentifier = ($this->identifierNormalizer)();
            $this->identifierNormalizer = null;
        }

        return $this->userIdentifier;
    }

    public function getAttributes(): ?array
    {
        return $this->attributes;
    }

    /**
     * @throws AuthenticationException when the user cannot be found
     */
    public function getUser(): UserInterface
    {
        if (isset($this->user)) {
            return $this->user;
        }

        if (null === $this->userLoader) {
            throw new \LogicException(\sprintf('No user loader is configured, did you forget to register the "%s" listener?', UserProviderListener::class));
        }

        if (null === $this->getAttributes()) {
            $user = ($this->userLoader)($this->getUserIdentifier());
        } else {
            $user = ($this->userLoader)($this->getUserIdentifier(), $this->getAttributes());
        }

        // No user has been found via the $this->userLoader callback
        if (null === $user) {
            $exception = new UserNotFoundException();
            $exception->setUserIdentifier($this->getUserIdentifier());

            throw $exception;
        }

        if (!$user instanceof UserInterface) {
            throw new AuthenticationServiceException(\sprintf('The user provider must return a UserInterface object, "%s" given.', get_debug_type($user)));
        }

        return $this->user = $user;
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
