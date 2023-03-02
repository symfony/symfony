<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HttpFoundation\Tests\Session\Storage\Handler;

use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Session\Storage\Handler\RedisSessionHandler;
use Symfony\Component\HttpFoundation\Session\Storage\Handler\SessionHandlerFactory;
use Symfony\Component\HttpFoundation\Session\Storage\Handler\StrictSessionHandler;

/**
 * Test class for SessionHandlerFactory.
 *
 * @author Simon <simon.chrzanowski@quentic.com>
 *
 * @runTestsInSeparateProcesses
 *
 * @preserveGlobalState disabled
 */
class SessionHandlerFactoryTest extends TestCase
{
    /**
     * @dataProvider provideConnectionDSN
     */
    public function testCreateFileHandler(string $connectionDSN, string $expectedPath, string $expectedHandlerType)
    {
        $handler = SessionHandlerFactory::createHandler($connectionDSN);

        $this->assertInstanceOf($expectedHandlerType, $handler);
        $this->assertEquals($expectedPath, \ini_get('session.save_path'));
    }

    public static function provideConnectionDSN(): array
    {
        $base = sys_get_temp_dir();

        return [
            'native file handler using save_path from php.ini' => ['connectionDSN' => 'file://', 'expectedPath' => \ini_get('session.save_path'), 'expectedHandlerType' => StrictSessionHandler::class],
            'native file handler using provided save_path' => ['connectionDSN' => 'file://'.$base.'/session/storage', 'expectedPath' => $base.'/session/storage', 'expectedHandlerType' => StrictSessionHandler::class],
        ];
    }

    /**
     * @requires extension redis
     */
    public function testCreateRedisHandlerFromConnectionObject()
    {
        $handler = SessionHandlerFactory::createHandler($this->createMock(\Redis::class));
        $this->assertInstanceOf(RedisSessionHandler::class, $handler);
    }

    /**
     * @requires extension redis
     */
    public function testCreateRedisHandlerFromDsn()
    {
        $handler = SessionHandlerFactory::createHandler('redis://localhost?prefix=foo&ttl=3600&ignored=bar');
        $this->assertInstanceOf(RedisSessionHandler::class, $handler);

        $reflection = new \ReflectionObject($handler);

        $prefixProperty = $reflection->getProperty('prefix');
        $this->assertSame('foo', $prefixProperty->getValue($handler));

        $ttlProperty = $reflection->getProperty('ttl');
        $this->assertSame(3600, $ttlProperty->getValue($handler));

        $handler = SessionHandlerFactory::createHandler('redis://localhost?prefix=foo&ttl=3600&ignored=bar', ['ttl' => fn () => 123]);

        $this->assertInstanceOf(\Closure::class, $reflection->getProperty('ttl')->getValue($handler));
    }
}
