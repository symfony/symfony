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

/**
 * @requires PHP 7.0
 */
class AbstractSessionHandlerTest extends TestCase
{
    private static $server;

    public static function setUpBeforeClass()
    {
        $spec = [
            1 => ['file', '/dev/null', 'w'],
            2 => ['file', '/dev/null', 'w'],
        ];
        if (!self::$server = @proc_open('exec php -S localhost:8053', $spec, $pipes, __DIR__.'/Fixtures')) {
            self::markTestSkipped('PHP server unable to start.');
        }
        sleep(1);
    }

    public static function tearDownAfterClass()
    {
        if (self::$server) {
            proc_terminate(self::$server);
            proc_close(self::$server);
        }
    }

    /**
     * @dataProvider provideSession
     */
    public function testSession($fixture)
    {
        $context = ['http' => ['header' => "Cookie: sid=123abc\r\n"]];
        $context = stream_context_create($context);
        $result = file_get_contents(sprintf('http://localhost:8053/%s.php', $fixture), false, $context);

        $this->assertStringEqualsFile(__DIR__.sprintf('/Fixtures/%s.expected', $fixture), $result);
    }

    public function provideSession()
    {
        foreach (glob(__DIR__.'/Fixtures/*.php') as $file) {
            yield [pathinfo($file, PATHINFO_FILENAME)];
        }
    }
}
