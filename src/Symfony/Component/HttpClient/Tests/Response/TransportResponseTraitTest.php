<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HttpClient\Tests\Response;

use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\AmpHttpClient;
use Symfony\Component\HttpClient\CurlHttpClient;
use Symfony\Component\HttpClient\Exception\ServerException;
use Symfony\Component\HttpClient\NativeHttpClient;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\Test\TestHttpServer;

/**
 * @requires extension zlib
 */
class TransportResponseTraitTest extends TestCase
{
    private const PORT = 8097;

    public static function setUpBeforeClass(): void
    {
        TestHttpServer::start(self::PORT, __DIR__.'/../Fixtures/inflate');
    }

    public static function tearDownAfterClass(): void
    {
        TestHttpServer::stop(self::PORT);
    }

    public static function provideClients(): iterable
    {
        yield 'native' => [NativeHttpClient::class];
        yield 'curl' => [CurlHttpClient::class];
        yield 'amp' => [AmpHttpClient::class];
    }

    /**
     * @dataProvider provideClients
     */
    public function testHighlyCompressedBodyIsAccepted(string $clientClass)
    {
        $response = $this->createClient($clientClass)->request('GET', $this->url('/matrix'));

        $this->assertSame(json_encode(array_fill(0, 1500, array_fill(0, 1500, 0))), $response->getContent());
    }

    /**
     * @dataProvider provideClients
     */
    public function testHighlyCompressedErrorBodyStaysReadableWhenTheResponseIsDiscarded(string $clientClass)
    {
        $client = $this->createClient($clientClass);
        $url = $this->url('/matrix-error');

        try {
            $client->request('GET', $url);
            $this->fail(ServerException::class.' expected');
        } catch (ServerException $e) {
            $this->assertSame(\sprintf('HTTP/1.1 500 Internal Server Error returned for "%s".', $url), $e->getMessage());
        }

        $this->assertSame(json_encode(array_fill(0, 1500, array_fill(0, 1500, 0))), $e->getResponse()->getContent(false));
    }

    private function createClient(string $clientClass): HttpClientInterface
    {
        if (CurlHttpClient::class === $clientClass && !\extension_loaded('curl')) {
            $this->markTestSkipped('The "curl" extension is not available.');
        }

        return new $clientClass();
    }

    private function url(string $path): string
    {
        return 'http://localhost:'.self::PORT.$path;
    }
}
