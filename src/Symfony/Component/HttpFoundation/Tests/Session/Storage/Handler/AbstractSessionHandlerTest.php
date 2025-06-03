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

class AbstractSessionHandlerTest extends TestCase
{
    /** @var resource|false */
    private static $server;

    public static function setUpBeforeClass(): void
    {
        $spec = [
            1 => ['file', '/dev/null', 'w'],
            2 => ['file', '/dev/null', 'w'],
        ];
        if (!self::$server = @proc_open('exec '.\PHP_BINARY.' -S localhost:8053', $spec, $pipes, __DIR__.'/Fixtures')) {
            self::markTestSkipped('PHP server unable to start.');
        }
        sleep(1);
    }

    public static function tearDownAfterClass(): void
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
        $result = file_get_contents(\sprintf('http://localhost:8053/%s.php', $fixture), false, $context);
        $result = preg_replace_callback('/expires=[^;]++/', fn ($m) => str_replace('-', ' ', $m[0]), $result);

        $this->assertStringEqualsFile(__DIR__.\sprintf('/Fixtures/%s.expected', $fixture), $result);
    }

    public static function provideSession()
    {
        foreach (glob(__DIR__.'/Fixtures/*.php') as $file) {
            yield [pathinfo($file, \PATHINFO_FILENAME)];
        }
    }
}
