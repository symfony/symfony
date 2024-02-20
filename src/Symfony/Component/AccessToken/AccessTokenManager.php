<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare (strict_types=1);

namespace Symfony\Component\AccessToken;

use Symfony\Component\AccessToken\Bridge\OAuth\ClientCredentialsProvider;
use Symfony\Component\AccessToken\Bridge\OAuth\OAuthFactory;
use Symfony\Component\AccessToken\Bridge\OAuth\RefreshTokenProvider;
use Symfony\Component\AccessToken\Credentials\Dsn;
use Symfony\Component\AccessToken\Credentials\FactoryInterface;
use Symfony\Component\AccessToken\Exception\FactoryNotFoundException;
use Symfony\Component\AccessToken\Exception\ProviderNotFoundException;
use Symfony\Component\HttpClient\HttpClient;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * @author Pierre Rineau <pierre.rineau@processus.org>
 */
class AccessTokenManager implements AccessTokenManagerInterface
{
    private null|array $providers;
    private null|array $factories;

    /**
     * @param array<ProviderInterface> $providers
     */
    public function __construct(null|iterable $providers = null, null|iterable $factories = null, ?HttpClientInterface $httpClient = null)
    {
        $this->factories = $this->createDefaultFactories();
        if (null !== $factories) {
            foreach ($factories as $factory) {
                $this->factories[] = $factory;
            }
        }

        $this->providers = $this->createDefaultProviders($httpClient ?? HttpClient::create());
        if (null !== $providers) {
            foreach ($providers as $provider) {
                $this->providers[] = $provider;
            }
        }
    }

    #[\Override]
    public function createCredentials(string $uri): CredentialsInterface
    {
        $dsn = Dsn::fromString($uri);

        return $this->getFactory($dsn)->createCredentials($dsn);
    }

    #[\Override]
    public function getAccessToken(CredentialsInterface $credentials): AccessTokenInterface
    {
        $token = $this->getProvider($credentials)->getAccessToken($credentials);

        return $token->hasExpired() ? $this->refreshAccessToken($credentials) : $token;
    }

    #[\Override]
    public function refreshAccessToken(CredentialsInterface $credentials): AccessTokenInterface
    {
        return $this->getProvider($credentials)->refreshAccessToken($credentials);
    }

    #[\Override]
    public function deleteAccessToken(CredentialsInterface $credentials): void
    {
    }

    /**
     * Get provider for given credentials.
     */
    protected function getFactory(Dsn $dsn): FactoryInterface
    {
        return $this->factories[$dsn->getScheme()] ?? throw new FactoryNotFoundException(\sprintf("Credentials factory for scheme '%s' was not found.", $dsn->getScheme()));
    }

    /**
     * Get provider for given credentials.
     */
    protected function getProvider(CredentialsInterface $credentials): ProviderInterface
    {
        foreach ($this->providers as $provider) {
            \assert($provider instanceof ProviderInterface);

            if ($provider->supports($credentials)) {
                return $provider;
            }
        }

        throw new ProviderNotFoundException(\sprintf("Access token provider for credentials '%s' with class '%s' was not found.", $credentials->getId(), \get_class($credentials)));
    }

    protected function createDefaultFactories(): iterable
    {
        return [
            'oauth' => new OAuthFactory(),
        ];
    }

    protected function createDefaultProviders(HttpClientInterface $httpClient): iterable
    {
        return [
            new ClientCredentialsProvider($httpClient),
            new RefreshTokenProvider($httpClient),
        ];
    }
}

