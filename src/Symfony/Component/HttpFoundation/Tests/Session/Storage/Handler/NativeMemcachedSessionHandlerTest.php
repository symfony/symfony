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
use Symfony\Component\HttpFoundation\Session\Storage\Handler\NativeMemcachedSessionHandler;
use Symfony\Component\HttpFoundation\Session\Storage\NativeSessionStorage;

/**
 * Test class for NativeMemcachedSessionHandler.
 *
 * @author Maurits van der Schee <maurits@vdschee.nl>
 *
 * @runTestsInSeparateProcesses
 * @preserveGlobalState disabled
 */
class NativeMemcachedSessionHandlerTest extends TestCase
{
    /**
     * @dataProvider savePathDataProvider
     */
    public function testConstruct(string $savePath, array $sessionOptions, string $expectedSavePath, string $expectedSessionName)
    {
        ini_set('session.save_path', '/var/lib/php/sessions');
        ini_set('session.name', 'PHPSESSID');

        new NativeSessionStorage($sessionOptions, new NativeMemcachedSessionHandler($savePath, $sessionOptions));

        $this->assertEquals($expectedSessionName, ini_get('session.name'));
        $this->assertEquals($expectedSavePath, ini_get('session.save_path'));
        $this->assertTrue((bool) ini_get('memcached.sess_locking'));
    }

    public function savePathDataProvider()
    {
        return [
            ['localhost:11211', ['name' => 'TESTING'], 'localhost:11211', 'TESTING'],
            ['', ['name' => 'TESTING'], '', 'TESTING'],
            ['', [], '', 'PHPSESSID'],
        ];
    }

    public function testConstructDefault()
    {
        ini_set('session.save_path', 'localhost:11211');
        ini_set('session.name', 'TESTING');

        new NativeSessionStorage([], new NativeMemcachedSessionHandler());

        $this->assertEquals('localhost:11211', ini_get('session.save_path'));
        $this->assertEquals('TESTING', ini_get('session.name'));
        $this->assertTrue((bool) ini_get('memcached.sess_locking'));
    }
}
