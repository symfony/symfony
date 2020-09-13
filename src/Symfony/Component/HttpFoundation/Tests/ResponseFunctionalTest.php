<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HttpFoundation\Tests;

use PHPUnit\Framework\TestCase;

class ResponseFunctionalTest extends TestCase
{
    private static $server;

    public static function setUpBeforeClass(): void
    {
        $spec = [
            1 => ['file', '/dev/null', 'w'],
            2 => ['file', '/dev/null', 'w'],
        ];
        if (!self::$server = @proc_open('exec php -S localhost:8054', $spec, $pipes, __DIR__.'/Fixtures/response-functional')) {
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
     * @dataProvider provideCookie
     */
    public function testCookie($fixture)
    {
        if (\PHP_VERSION_ID >= 80000 && 'cookie_max_age' === $fixture) {
            $this->markTestSkipped('This fixture produces a fatal error on PHP 8.');
        }

        $result = file_get_contents(sprintf('http://localhost:8054/%s.php', $fixture));
        $this->assertStringMatchesFormatFile(__DIR__.sprintf('/Fixtures/response-functional/%s.expected', $fixture), $result);
    }

    public function provideCookie()
    {
        foreach (glob(__DIR__.'/Fixtures/response-functional/*.php') as $file) {
            yield [pathinfo($file, \PATHINFO_FILENAME)];
        }
    }
}
