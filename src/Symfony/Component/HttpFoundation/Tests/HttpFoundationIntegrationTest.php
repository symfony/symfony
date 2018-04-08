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
use Symfony\Component\Process\Process;

class HttpFoundationIntegrationTest extends TestCase
{
    private $host = 'localhost:55876';

    /** @var Process */
    private $process;

    protected function setUp()
    {
        $this->startServer();
    }

    protected function tearDown()
    {
        $this->stopServer();
    }

    public function testLaxSameSiteCookieIsProperlySent()
    {
        $cookieHeader = array_filter(
            get_headers('http://'.$this->host.'/cookie/samesite-lax'),
            function ($header) {
                return 0 === strpos($header, 'Set-Cookie: ');
            }
        );

        self::assertCount(1, $cookieHeader);
        self::assertSame(
            'Set-Cookie: SF=V; path=/cookie; samesite=lax; domain=example.org; secure; HttpOnly',
            current($cookieHeader)
        );
    }

    public function testStrictSameSiteCookieIsProperlySent()
    {
        $cookieHeader = array_filter(
            get_headers('http://'.$this->host.'/cookie/samesite-strict'),
            function ($header) {
                return 0 === strpos($header, 'Set-Cookie: ');
            }
        );

        self::assertCount(1, $cookieHeader);
        self::assertSame(
            'Set-Cookie: SF=V; path=/; samesite=strict; secure; HttpOnly',
            current($cookieHeader)
        );
    }

    private function startServer()
    {
        $this->process = new Process('php -S '.$this->host.' '.__DIR__.'/Fixtures/server.php&>/dev/null');
        $this->process->start();

        $count = 0;
        do {
            $result = @file_get_contents('http://'.$this->host.'/ping');
            usleep(100);
        } while ('pong' !== $result && ++$count < 10);
    }

    private function stopServer()
    {
        $this->process->stop();
    }
}
