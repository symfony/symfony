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
use Symfony\Bridge\PhpUnit\ForwardCompatTestTrait;

class ResponseFunctionalTest extends TestCase
{
    use ForwardCompatTestTrait;

    private static $server;

    private static function doSetUpBeforeClass()
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

    private static function doTearDownAfterClass()
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
        $result = file_get_contents(sprintf('http://localhost:8054/%s.php', $fixture));
        $this->assertStringMatchesFormatFile(__DIR__.sprintf('/Fixtures/response-functional/%s.expected', $fixture), $result);
    }

    public function provideCookie()
    {
        foreach (glob(__DIR__.'/Fixtures/response-functional/*.php') as $file) {
            yield [pathinfo($file, PATHINFO_FILENAME)];
        }
    }
}
