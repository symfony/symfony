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

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\RequiresPhp;
use PHPUnit\Framework\TestCase;

#[RequiresPhp('>=8.4')]
class RequestFunctionalTest extends TestCase
{
    /** @var resource|false */
    private static $server;

    public static function setUpBeforeClass(): void
    {
        $spec = [
            1 => ['file', '/dev/null', 'w'],
            2 => ['file', '/dev/null', 'w'],
        ];
        if (!self::$server = @proc_open('exec '.\PHP_BINARY.' -S localhost:8054', $spec, $pipes, __DIR__.'/Fixtures/request-functional')) {
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

    public static function provideMethodsRequiringExplicitBodyParsing()
    {
        return [
            ['PUT'],
            ['DELETE'],
            ['PATCH'],
            // PHP’s built-in server doesn’t support QUERY
        ];
    }

    #[DataProvider('provideMethodsRequiringExplicitBodyParsing')]
    public function testFormUrlEncodedBodyParsing(string $method)
    {
        $response = file_get_contents('http://localhost:8054/', false, stream_context_create([
            'http' => [
                'header' => 'Content-type: application/x-www-form-urlencoded',
                'method' => $method,
                'content' => http_build_query(['foo' => 'bar']),
            ],
        ]));

        $this->assertSame(['foo' => 'bar'], json_decode($response, true)['request']);
    }

    #[DataProvider('provideMethodsRequiringExplicitBodyParsing')]
    public function testMultipartFormDataBodyParsing(string $method)
    {
        $response = file_get_contents('http://localhost:8054/', false, stream_context_create([
            'http' => [
                'header' => 'Content-Type: multipart/form-data; boundary=boundary',
                'method' => $method,
                'content' => "--boundary\r\n".
                    "Content-Disposition: form-data; name=foo\r\n".
                    "\r\n".
                    "bar\r\n".
                    "--boundary\r\n".
                    "Content-Disposition: form-data; name=baz; filename=baz.txt\r\n".
                    "Content-Type: text/plain\r\n".
                    "\r\n".
                    "qux\r\n".
                    '--boundary--',
            ],
        ]));

        $data = json_decode($response, true);

        $this->assertSame(['foo' => 'bar'], $data['request']);
        $this->assertSame(['baz' => [
            'clientOriginalName' => 'baz.txt',
            'clientMimeType' => 'text/plain',
            'content' => 'qux',
        ]], $data['files']);
    }
}
