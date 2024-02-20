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

namespace Symfony\Component\AccessToken\Tests\Unit\Manager;

use PHPUnit\Framework\TestCase;
use Symfony\Component\AccessToken\AccessToken;
use Symfony\Component\AccessToken\AccessTokenManagerInterface;
use Symfony\Component\AccessToken\Credentials\BasicAuthCredentials;
use Symfony\Component\AccessToken\Manager\CacheAccessTokenManagerDecorator;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Component\Cache\Adapter\NullAdapter;

/**
 * @author Pierre Rineau <pierre.rineau@processus.org>
 */
class CacheAccessTokenManagerDecoratorTest extends TestCase
{
    public function testGetAccessToken(): void
    {
        $cachedAccessToken = new AccessToken('cached');

        $accessManager = $this->createMock(AccessTokenManagerInterface::class);
        $accessManager->expects($this->never())->method('refreshAccessToken');
        $accessManager->expects($this->never())->method('getAccessToken');

        $cache = $this->createMock(CacheInterface::class);
        $cache->expects($this->once())->method('get')->willReturn($cachedAccessToken);

        $tested = new CacheAccessTokenManagerDecorator($accessManager, $cache);

        $fetchedAccessToken = $tested->getAccessToken(new BasicAuthCredentials('foo'));
        self::assertSame($cachedAccessToken, $fetchedAccessToken);
    }

    public function testGetAccessTokenWhenCacheMiss(): void
    {
        $freshAccessToken = new AccessToken('fresh');

        $accessManager = $this->createMock(AccessTokenManagerInterface::class);
        $accessManager->expects($this->never())->method('refreshAccessToken');
        $accessManager->expects($this->once())->method('getAccessToken')->willReturn($freshAccessToken);

        $cache = new NullAdapter();

        $tested = new CacheAccessTokenManagerDecorator($accessManager, $cache);

        $fetchedAccessToken = $tested->getAccessToken(new BasicAuthCredentials('foo'));
        self::assertSame($freshAccessToken, $fetchedAccessToken);
    }

    public function testRefreshAcessTokenAlwaysRecompute(): void
    {
        $cachedAccessToken = new AccessToken('cached');
        $freshAccessToken = new AccessToken('fresh');

        $accessManager = $this->createMock(AccessTokenManagerInterface::class);
        $accessManager->expects($this->never())->method('getAccessToken');
        $accessManager->expects($this->once())->method('refreshAccessToken')->willReturn($freshAccessToken);

        $cache = new NullAdapter();

        $tested = new CacheAccessTokenManagerDecorator($accessManager, $cache);

        $fetchedAccessToken = $tested->refreshAccessToken(new BasicAuthCredentials('foo'));
        self::assertSame($freshAccessToken, $fetchedAccessToken);
        self::assertNotSame($cachedAccessToken, $fetchedAccessToken);
    }

    public function testDeleteAcessToken(): void
    {
        $accessManager = $this->createMock(AccessTokenManagerInterface::class);
        $accessManager->expects($this->never())->method('refreshAccessToken');
        $accessManager->expects($this->never())->method('getAccessToken');
        $accessManager->expects($this->once())->method('deleteAccessToken');

        $cache = $this->createMock(CacheInterface::class);
        $cache->expects($this->never())->method('get');
        $cache->expects($this->once())->method('delete');

        $tested = new CacheAccessTokenManagerDecorator($accessManager, $cache);

        $tested->deleteAccessToken(new BasicAuthCredentials('foo'));
    }
}
