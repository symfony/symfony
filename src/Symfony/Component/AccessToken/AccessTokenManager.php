<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

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
    private array $providers;
    private array $factories;

    /**
     * @param iterable<ProviderInterface>       $providers
     * @param iterable<string,FactoryInterface> $factories
     */
    public function __construct(?iterable $providers = null, ?iterable $factories = null, ?HttpClientInterface $httpClient = null)
    {
        $this->factories = $this->createDefaultFactories();
        if (null !== $factories) {
            foreach ($factories as $scheme => $factory) {
                $this->factories[$scheme] = $factory;
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
        return $this->factories[$dsn->getScheme()] ?? throw new FactoryNotFoundException(sprintf('Credentials factory for scheme "%s" was not found.', $dsn->getScheme()));
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

        throw new ProviderNotFoundException(sprintf('Access token provider for credentials "%s" with class "%s" was not found.', $credentials->getId(), $credentials::class));
    }

    /** @return array<string,FactoryInterface> */
    protected function createDefaultFactories(): array
    {
        return [
            'oauth' => new OAuthFactory(),
        ];
    }

    /** @return array<ProviderInterface> */
    protected function createDefaultProviders(HttpClientInterface $httpClient): array
    {
        return [
            new ClientCredentialsProvider($httpClient),
            new RefreshTokenProvider($httpClient),
        ];
    }
}
