<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

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
}
