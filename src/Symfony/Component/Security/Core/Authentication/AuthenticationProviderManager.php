<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Security\Core\Authentication;

use Symfony\Component\PasswordHasher\Exception\InvalidPasswordException;
use Symfony\Component\Security\Core\Authentication\Provider\AuthenticationProviderInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\AuthenticationEvents;
use Symfony\Component\Security\Core\Event\AuthenticationFailureEvent;
use Symfony\Component\Security\Core\Event\AuthenticationSuccessEvent;
use Symfony\Component\Security\Core\Exception\AccountStatusException;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Core\Exception\BadCredentialsException;
use Symfony\Component\Security\Core\Exception\ProviderNotFoundException;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

trigger_deprecation('symfony/security-core', '5.3', 'The "%s" class is deprecated, use the new authenticator system instead.', AuthenticationProviderManager::class);

// Help opcache.preload discover always-needed symbols
class_exists(AuthenticationEvents::class);
class_exists(AuthenticationFailureEvent::class);
class_exists(AuthenticationSuccessEvent::class);

/**
 * AuthenticationProviderManager uses a list of AuthenticationProviderInterface
 * instances to authenticate a Token.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 * @author Johannes M. Schmitt <schmittjoh@gmail.com>
 *
 * @deprecated since Symfony 5.3, use the new authenticator system instead
 */
class AuthenticationProviderManager implements AuthenticationManagerInterface
{
    private $providers;
    private $eraseCredentials;
    private $eventDispatcher;

    /**
     * @param iterable<mixed, AuthenticationProviderInterface> $providers        An iterable with AuthenticationProviderInterface instances as values
     * @param bool                                             $eraseCredentials Whether to erase credentials after authentication or not
     *
     * @throws \InvalidArgumentException
     */
    public function __construct(iterable $providers, bool $eraseCredentials = true)
    {
        if (!$providers) {
            throw new \InvalidArgumentException('You must at least add one authentication provider.');
        }

        $this->providers = $providers;
        $this->eraseCredentials = $eraseCredentials;
    }

    public function setEventDispatcher(EventDispatcherInterface $dispatcher)
    {
        $this->eventDispatcher = $dispatcher;
    }

    /**
     * {@inheritdoc}
     */
    public function authenticate(TokenInterface $token)
    {
        $lastException = null;
        $result = null;

        foreach ($this->providers as $provider) {
            if (!$provider instanceof AuthenticationProviderInterface) {
                throw new \InvalidArgumentException(sprintf('Provider "%s" must implement the AuthenticationProviderInterface.', get_debug_type($provider)));
            }

            if (!$provider->supports($token)) {
                continue;
            }

            try {
                $result = $provider->authenticate($token);

                if (null !== $result) {
                    break;
                }
            } catch (AccountStatusException $e) {
                $lastException = $e;

                break;
            } catch (AuthenticationException $e) {
                $lastException = $e;
            } catch (InvalidPasswordException $e) {
                $lastException = new BadCredentialsException('Bad credentials.', 0, $e);
            }
        }

        if (null !== $result) {
            if (true === $this->eraseCredentials) {
                $result->eraseCredentials();
            }

            if (null !== $this->eventDispatcher) {
                $this->eventDispatcher->dispatch(new AuthenticationSuccessEvent($result), AuthenticationEvents::AUTHENTICATION_SUCCESS);
            }

            // @deprecated since Symfony 5.3
            if ($result->getUser() instanceof UserInterface && !method_exists($result->getUser(), 'getUserIdentifier')) {
                trigger_deprecation('symfony/security-core', '5.3', 'Not implementing method "getUserIdentifier(): string" in user class "%s" is deprecated. This method will replace "getUsername()" in Symfony 6.0.', get_debug_type($result->getUser()));
            }

            return $result;
        }

        if (null === $lastException) {
            $lastException = new ProviderNotFoundException(sprintf('No Authentication Provider found for token of class "%s".', \get_class($token)));
        }

        if (null !== $this->eventDispatcher) {
            $this->eventDispatcher->dispatch(new AuthenticationFailureEvent($token, $lastException), AuthenticationEvents::AUTHENTICATION_FAILURE);
        }

        $lastException->setToken($token);

        throw $lastException;
    }
}
