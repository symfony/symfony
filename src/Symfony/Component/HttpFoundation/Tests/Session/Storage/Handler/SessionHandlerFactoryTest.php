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
        $handler = SessionHandlerFactory::createHandler($this->createStub(\Redis::class));
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

        $handler = SessionHandlerFactory::createHandler('redis://localhost?prefix=foo&ttl=3600&ignored=bar', ['ttl' => static fn () => 123]);

        $this->assertInstanceOf(\Closure::class, $reflection->getProperty('ttl')->getValue($handler));
    }

    public function testCredentialsAreNotExposedInTrace()
    {
        if (!class_exists(\SensitiveParameterValue::class)) {
            $this->markTestSkipped('Sensitive parameter support requires PHP 8.2.');
        }

        $previous = ini_set('zend.exception_ignore_args', '0');
        try {
            foreach (['redis://s3cr3t@localhost:999999', 'redis://localhost?auth=s3cr3t&host=foo'] as $dsn) {
                try {
                    SessionHandlerFactory::createHandler($dsn, []);
                    $this->fail('An exception should have been thrown.');
                } catch (\InvalidArgumentException $e) {
                    $this->assertStringNotContainsString('s3cr3t', json_encode($e->getTrace(), \JSON_THROW_ON_ERROR));
                }
            }
        } finally {
            ini_set('zend.exception_ignore_args', $previous);
        }
    }

    public function testCreateHandlerWithUnsupportedDsnDoesNotLeakCredentials()
    {
        try {
            SessionHandlerFactory::createHandler('unsupported://user:s3cr3t@localhost');
            $this->fail('An exception should have been thrown.');
        } catch (\InvalidArgumentException $e) {
            $this->assertSame('No session handler supports the given DSN.', $e->getMessage());
            $this->assertStringNotContainsString('s3cr3t', $e->getMessage());
        }
    }
}
