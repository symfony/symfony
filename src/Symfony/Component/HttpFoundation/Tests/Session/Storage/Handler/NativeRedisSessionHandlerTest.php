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
use Symfony\Component\HttpFoundation\Session\Storage\Handler\NativeRedisSessionHandler;
use Symfony\Component\HttpFoundation\Session\Storage\NativeSessionStorage;

/**
 * Test class for NativeRedisSessionHandler.
 *
 * @author Maurits van der Schee <maurits@vdschee.nl>
 *
 * @runTestsInSeparateProcesses
 * @preserveGlobalState disabled
 */
class NativeRedisSessionHandlerTest extends TestCase
{
    /**
     * @dataProvider savePathDataProvider
     */
    public function testConstruct(string $savePath, array $sessionOptions, string $expectedSavePath, string $expectedSessionName)
    {
        ini_set('session.save_path', '/var/lib/php/sessions');
        ini_set('session.name', 'PHPSESSID');

        new NativeSessionStorage($sessionOptions, new NativeRedisSessionHandler($savePath, $sessionOptions));

        $this->assertEquals($expectedSessionName, ini_get('session.name'));
        $this->assertEquals($expectedSavePath, ini_get('session.save_path'));
        $this->assertTrue((bool) ini_get('redis.session.locking_enabled'));
    }

    public function savePathDataProvider()
    {
        return [
            ['tcp://localhost:6379', [], 'tcp://localhost:6379?prefix=PHPREDIS_SESSION.PHPSESSID.', 'PHPSESSID'],
            ['tcp://localhost:6379', ['name' => 'TESTING'], 'tcp://localhost:6379?prefix=PHPREDIS_SESSION.TESTING.', 'TESTING'],
            ['tcp://localhost:6379?prefix=CUSTOM.', ['name' => 'TESTING'], 'tcp://localhost:6379?prefix=CUSTOM.', 'TESTING'],
            ['tcp://localhost:6379?prefix=CUSTOM.&prefix=CUSTOM2.', ['name' => 'TESTING'], 'tcp://localhost:6379?prefix=CUSTOM2.', 'TESTING'],
        ];
    }

    public function testConstructDefault()
    {
        ini_set('session.save_path', 'tcp://localhost:6379');
        ini_set('session.name', 'TESTING');

        new NativeSessionStorage([], new NativeRedisSessionHandler());

        $this->assertEquals('tcp://localhost:6379?prefix=PHPREDIS_SESSION.TESTING.', ini_get('session.save_path'));
        $this->assertEquals('TESTING', ini_get('session.name'));
        $this->assertTrue((bool) ini_get('redis.session.locking_enabled'));
    }
}
