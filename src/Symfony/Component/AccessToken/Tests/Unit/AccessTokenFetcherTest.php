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
use Symfony\Component\AccessToken\AccessTokenFetcher;
use Symfony\Component\AccessToken\AccessTokenManagerInterface;
use Symfony\Component\AccessToken\Credentials\BasicAuthCredentials;

/**
 * @author Pierre Rineau <pierre.rineau@processus.org>
 */
class AccessTokenFetcherTest extends TestCase
{
    public function testGetAccessToken(): void
    {
        $accessToken = new AccessToken('foo');

        $manager = $this->createMock(AccessTokenManagerInterface::class);
        $manager->expects($this->never())->method('refreshAccessToken');
        $manager->expects($this->once())->method('getAccessToken')->willReturn($accessToken);

        $fetcher = new AccessTokenFetcher($manager, new BasicAuthCredentials('foo'));

        self::assertSame($accessToken, $fetcher->getAccessToken());
    }

    public function testRefreshAccessToken(): void
    {
        $accessToken = new AccessToken('foo');

        $manager = $this->createMock(AccessTokenManagerInterface::class);
        $manager->expects($this->never())->method('getAccessToken');
        $manager->expects($this->once())->method('refreshAccessToken')->willReturn($accessToken);

        $fetcher = new AccessTokenFetcher($manager, new BasicAuthCredentials('foo'));

        self::assertSame($accessToken, $fetcher->refreshAccessToken());
    }

    public function testDeleteAccessToken(): void
    {
        $manager = $this->createMock(AccessTokenManagerInterface::class);
        $manager->expects($this->never())->method('getAccessToken');
        $manager->expects($this->never())->method('refreshAccessToken');
        $manager->expects($this->once())->method('deleteAccessToken');

        $fetcher = new AccessTokenFetcher($manager, new BasicAuthCredentials('foo'));
        $fetcher->deleteAccessToken();
    }

    public function testCreateWithUri(): void
    {
        $manager = $this->createMock(AccessTokenManagerInterface::class);
        $manager->expects($this->never())->method('getAccessToken');
        $manager->expects($this->never())->method('refreshAccessToken');

        $fetcher = AccessTokenFetcher::createWithUri($manager, 'oauth://example.tld');
        self::assertInstanceOf(AccessTokenFetcher::class, $fetcher);
    }

    public function testCreateCredentialsWithUri(): void
    {
        $manager = $this->createMock(AccessTokenManagerInterface::class);
        $manager->expects($this->never())->method('getAccessToken');
        $manager->expects($this->never())->method('refreshAccessToken');
        $manager->expects($this->once())->method('createCredentials')->willReturn(new BasicAuthCredentials('some_user'));

        $credentials = AccessTokenFetcher::createCredentialsWithUri($manager, 'oauth://example.tld');
        self::assertInstanceOf(BasicAuthCredentials::class, $credentials);
        self::assertSame('some_user', $credentials->getUsername());
    }
}
