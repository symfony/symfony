<?php

namespace Symfony\Component\Security\Authentication;

use Symfony\Component\Security\Exception\AccountStatusException;
use Symfony\Component\Security\Exception\AuthenticationException;
use Symfony\Component\Security\Exception\ProviderNotFoundException;
use Symfony\Component\Security\Authentication\Provider\AuthenticationProviderInterface;
use Symfony\Component\Security\Authentication\Token\TokenInterface;

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

/**
 * AuthenticationProviderManager uses a list of AuthenticationProviderInterface
 * instances to authenticate a Token.
 *
 * @author Fabien Potencier <fabien.potencier@symfony-project.com>
 */
class AuthenticationProviderManager implements AuthenticationManagerInterface
{
    protected $providers;
    protected $eraseCredentials;

    /**
     * Constructor.
     *
     * @param AuthenticationProviderInterface[] $providers        An array of AuthenticationProviderInterface instances
     * @param Boolean                           $eraseCredentials Whether to erase credentials after authentication or not
     */
    public function __construct(array $providers = array(), $eraseCredentials = true)
    {
        $this->setProviders($providers);
        $this->eraseCredentials = (Boolean) $eraseCredentials;
    }

    /**
     * {@inheritdoc}
     */
    public function authenticate(TokenInterface $token)
    {
        if (!count($this->providers)) {
            throw new \LogicException('You must add at least one provider.');
        }

        $lastException = null;
        $result = null;

        foreach ($this->providers as $provider) {
            if (!$provider->supports($token)) {
                continue;
            }

            try {
                $result = $provider->authenticate($token);
            } catch (AccountStatusException $e) {
                $e->setExtraInformation($token);

                throw $e;
            } catch (AuthenticationException $e) {
                $lastException = $e;
            }
        }

        if (null !== $result) {
            if (true === $this->eraseCredentials) {
                $result->eraseCredentials();
            }

            return $result;
        }

        if (null === $lastException) {
            $lastException = new ProviderNotFoundException(sprintf('No Authentication Provider found for token of class "%s".', get_class($token)));
        }

        $lastException->setExtraInformation($token);

        throw $lastException;
    }

    /**
     * Returns the list of current providers.
     *
     * @return AuthenticationProviderInterface[] An array of AuthenticationProviderInterface instances
     */
    public function all()
    {
        return $this->providers;
    }

    /**
     * Sets the providers instances.
     *
     * @param AuthenticationProviderInterface[] $providers An array of AuthenticationProviderInterface instances
     */
    public function setProviders(array $providers)
    {
        $this->providers = array();
        foreach ($providers as $provider) {
            $this->add($provider);
        }
    }

    /**
     * Adds a provider.
     *
     * @param AuthenticationProviderInterface $provider A AuthenticationProviderInterface instance
     */
    public function add(AuthenticationProviderInterface $provider)
    {
        $this->providers[] = $provider;
    }
}
