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

namespace Symfony\Component\AccessToken\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Symfony\Component\AccessToken\AccessToken;
use Symfony\Component\AccessToken\AccessTokenManager;
use Symfony\Component\AccessToken\Bridge\OAuth\ClientCredentials;
use Symfony\Component\AccessToken\Credentials\BasicAuthCredentials;
use Symfony\Component\AccessToken\Exception\FactoryNotFoundException;
use Symfony\Component\AccessToken\Exception\ProviderNotFoundException;
use Symfony\Component\AccessToken\ProviderInterface;
use Symfony\Component\AccessToken\Tests\Mock\MockBasicAuthProvider;

/**
 * @author Pierre Rineau <pierre.rineau@processus.org>
 */
class AccessTokenManagerTest extends TestCase
{
    public function testGetAccessToken(): void
    {
        $manager = new AccessTokenManager([new MockBasicAuthProvider()]);

        $credentials = new BasicAuthCredentials('Foo', '');

        self::assertSame('getFoo', $manager->getAccessToken($credentials)->getValue());
    }

    public function testRefreshAccessToken(): void
    {
        $manager = new AccessTokenManager([new MockBasicAuthProvider()]);

        $credentials = new BasicAuthCredentials('Foo', '');

        self::assertSame('refreshFoo', $manager->refreshAccessToken($credentials)->getValue());
    }

    public function testGetAccessTokenRefreshesOnExpired(): void
    {
        $expiredToken = new AccessToken(value: 'token_value', expiresIn: 1, issuedAt: new \DateTimeImmutable('now -1 day'));
        self::assertTrue($expiredToken->hasExpired());

        $validToken = new AccessToken(value: 'other_value', expiresIn: 10000);
        self::assertFalse($validToken->hasExpired());

        $provider = $this->createMock(ProviderInterface::class);
        $provider->method('supports')->willReturn(true);
        $provider->method('getAccessToken')->willReturn($expiredToken);
        $provider->method('refreshAccessToken')->willReturn($validToken);

        $manager = new AccessTokenManager([$provider]);

        self::assertSame($validToken, $manager->getAccessToken(new BasicAuthCredentials('foo')));
    }

    public function testDeleteAccessToken(): void
    {
        $manager = new AccessTokenManager([new MockBasicAuthProvider()]);

        $credentials = new BasicAuthCredentials('Foo', '');
        $manager->deleteAccessToken($credentials);

        self::expectNotToPerformAssertions();
    }

    public function testUnfoundProviderRaiseError(): void
    {
        $manager = new AccessTokenManager();

        self::expectException(ProviderNotFoundException::class);
        $manager->getAccessToken(new BasicAuthCredentials('foo', ''));
    }

    public function testCreateCredentials(): void
    {
        $manager = new AccessTokenManager();

        $credentials = $manager->createCredentials('oauth://foo:bar@example.com');
        self::assertInstanceOf(ClientCredentials::class, $credentials);
    }

    public function testUnfoundFactoryRaiseError(): void
    {
        $manager = new AccessTokenManager();

        self::expectException(FactoryNotFoundException::class);
        $manager->createCredentials('unexisting://foo.bar@example.com');
    }
}
