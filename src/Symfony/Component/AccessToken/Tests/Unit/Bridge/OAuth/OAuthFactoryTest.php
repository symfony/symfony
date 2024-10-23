<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\AccessToken\Tests\Unit\Bridge\OAuth;

use PHPUnit\Framework\TestCase;
use Symfony\Component\AccessToken\Bridge\OAuth\ClientCredentials;
use Symfony\Component\AccessToken\Bridge\OAuth\OAuthFactory;
use Symfony\Component\AccessToken\Bridge\OAuth\RefreshTokenCredentials;
use Symfony\Component\AccessToken\Credentials\Dsn;
use Symfony\Component\AccessToken\Exception\InvalidArgumentException;

/**
 * @author Pierre Rineau <pierre.rineau@processus.org>
 */
class OAuthFactoryTest extends TestCase
{
    public function testDefaultsToClientCredentials(): void
    {
        $factory = new OAuthFactory();

        $uri = 'oauth://example.tld/oauth?client_id=fooId&client_secret=fooSecret&tenant=fooTenant&scope=fooScope%20barScope';
        $credentials = $factory->createCredentials(Dsn::fromString($uri));

        self::assertInstanceOf(ClientCredentials::class, $credentials);
    }

    public function testKeepsUnhandledParameters(): void
    {
        $factory = new OAuthFactory();

        $uri = 'oauth://foo:bar@example.tld/oauth?unexpected=value';
        $credentials = $factory->createCredentials(Dsn::fromString($uri));

        self::assertSame('https://example.tld/oauth?unexpected=value', $credentials->getEndpoint());
    }

    public function testUnknownGrantTypeRaiseException(): void
    {
        $factory = new OAuthFactory();

        $uri = 'oauth://example.tld/oauth?grant_type=UNKNOWN&client_id=fooId&client_secret=fooSecret&tenant=fooTenant&scope=fooScope%20barScope';

        self::expectException(InvalidArgumentException::class);
        $factory->createCredentials(Dsn::fromString($uri));
    }

    public function testClientCredentials(): void
    {
        $factory = new OAuthFactory();

        $uri = 'oauth://example.tld/oauth?grant_type=client_credentials&client_id=fooId&client_secret=fooSecret&tenant=fooTenant&scope=fooScope%20barScope&default_lifetime=12';
        $credentials = $factory->createCredentials(Dsn::fromString($uri));
        \assert($credentials instanceof ClientCredentials);

        self::assertInstanceOf(ClientCredentials::class, $credentials);
        self::assertSame('https://example.tld/oauth', $credentials->getEndpoint());
        self::assertSame('fooId', $credentials->getClientId());
        self::assertSame('fooSecret', $credentials->getClientSecret());
        self::assertSame('fooTenant', $credentials->getTenant());
        self::assertSame(['fooScope', 'barScope'], $credentials->getScope());
        self::assertSame(12, $credentials->getDefaultLifetime());
    }

    public function testClientCredentialsUserPassFallbackOnBasicAuth(): void
    {
        $factory = new OAuthFactory();

        $uri = 'oauth://fooId:fooSecret@example.tld/oauth?grant_type=client_credentials';
        $credentials = $factory->createCredentials(Dsn::fromString($uri));
        \assert($credentials instanceof ClientCredentials);

        self::assertSame('fooId', $credentials->getClientId());
        self::assertSame('fooSecret', $credentials->getClientSecret());
    }

    public function testClientCredentialsMissingClientIdRaiseException(): void
    {
        $factory = new OAuthFactory();

        $uri = 'oauth://example.tld/oauth?grant_type=client_credentials&client_secret=fooSecret&tenant=fooTenant&scope=fooScope%20barScope';

        self::expectException(InvalidArgumentException::class);
        $factory->createCredentials(Dsn::fromString($uri));
    }

    public function testClientCredentialsMissingSecretRaiseException(): void
    {
        $factory = new OAuthFactory();

        $uri = 'oauth://example.tld/oauth?grant_type=client_credentials&client_id=fooId&tenant=fooTenant&scope=fooScope%20barScope';

        self::expectException(InvalidArgumentException::class);
        $factory->createCredentials(Dsn::fromString($uri));
    }

    public function testRefreshTokenCredentials(): void
    {
        $factory = new OAuthFactory();

        $uri = 'oauth://example.tld/oauth?grant_type=refresh_token&refresh_token=the_token&client_id=fooId&client_secret=fooSecret&tenant=fooTenant&scope=fooScope%20barScope&default_lifetime=12';
        $credentials = $factory->createCredentials(Dsn::fromString($uri));
        \assert($credentials instanceof RefreshTokenCredentials);

        self::assertInstanceOf(RefreshTokenCredentials::class, $credentials);
        self::assertSame('https://example.tld/oauth', $credentials->getEndpoint());
        self::assertSame('the_token', $credentials->getRefreshToken());
        self::assertSame('fooId', $credentials->getClientId());
        self::assertSame('fooSecret', $credentials->getClientSecret());
        self::assertSame('fooTenant', $credentials->getTenant());
        self::assertSame(12, $credentials->getDefaultLifetime());
    }

    public function testRefreshTokenCredentialsBareMinimum(): void
    {
        $factory = new OAuthFactory();

        $uri = 'oauth://example.tld/oauth?grant_type=refresh_token&refresh_token=the_token';
        $credentials = $factory->createCredentials(Dsn::fromString($uri));
        \assert($credentials instanceof RefreshTokenCredentials);

        self::assertInstanceOf(RefreshTokenCredentials::class, $credentials);
        self::assertSame('https://example.tld/oauth', $credentials->getEndpoint());
        self::assertSame('the_token', $credentials->getRefreshToken());
        self::assertNull($credentials->getClientId());
        self::assertNull($credentials->getClientSecret());
        self::assertNull($credentials->getTenant());
    }

    public function testRefreshTokenCredentialsUserPassFallbackOnBasicAuth(): void
    {
        $factory = new OAuthFactory();

        $uri = 'oauth://fooId:fooSecret@example.tld/oauth?grant_type=refresh_token&refresh_token=the_token';
        $credentials = $factory->createCredentials(Dsn::fromString($uri));
        \assert($credentials instanceof RefreshTokenCredentials);

        self::assertSame('fooId', $credentials->getClientId());
        self::assertSame('fooSecret', $credentials->getClientSecret());
    }

    public function testRefreshTokenCredentialsMissingRefreshTokenRaiseException(): void
    {
        $factory = new OAuthFactory();

        $uri = 'oauth://example.tld/oauth?grant_type=refresh_token';

        self::expectException(InvalidArgumentException::class);
        $factory->createCredentials(Dsn::fromString($uri));
    }
}
