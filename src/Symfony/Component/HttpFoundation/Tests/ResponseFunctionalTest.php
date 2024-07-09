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
use Symfony\Component\Process\ExecutableFinder;
use Symfony\Component\Process\Process;

class ResponseFunctionalTest extends TestCase
{
    /** @var resource|false */
    private static $server;

    public static function setUpBeforeClass(): void
    {
        $spec = [
            1 => ['file', '/dev/null', 'w'],
            2 => ['file', '/dev/null', 'w'],
        ];
        if (!self::$server = @proc_open('exec '.\PHP_BINARY.' -S localhost:8054', $spec, $pipes, __DIR__.'/Fixtures/response-functional')) {
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
        $result = file_get_contents(\sprintf('http://localhost:8054/%s.php', $fixture));
        $result = preg_replace_callback('/expires=[^;]++/', fn ($m) => str_replace('-', ' ', $m[0]), $result);
        $this->assertStringMatchesFormatFile(__DIR__.\sprintf('/Fixtures/response-functional/%s.expected', $fixture), $result);
    }

    public static function provideCookie()
    {
        foreach (glob(__DIR__.'/Fixtures/response-functional/*.php') as $file) {
            if (str_contains($file, 'cookie')) {
                yield [pathinfo($file, \PATHINFO_FILENAME)];
            }
        }
    }

    /**
     * @group integration
     */
    public function testInformationalResponse()
    {
        if (!(new ExecutableFinder())->find('curl')) {
            $this->markTestSkipped('curl is not installed');
        }

        if (!($fp = @fsockopen('localhost', 80, $errorCode, $errorMessage, 2))) {
            $this->markTestSkipped('FrankenPHP is not running');
        }
        fclose($fp);

        $p = new Process(['curl', '-v', 'http://localhost/early_hints.php']);
        $p->run();
        $output = $p->getErrorOutput();

        $this->assertSame(3, preg_match_all('#Link: </css/style\.css>; rel="preload"; as="style"#', $output));
        $this->assertSame(2, preg_match_all('#Link: </js/app\.js>; rel="preload"; as="script"#', $output));
    }
}
