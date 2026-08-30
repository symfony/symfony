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

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\RequiresPhpExtension;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\AmpHttpClient;
use Symfony\Component\HttpClient\CurlHttpClient;
use Symfony\Component\HttpClient\Exception\ServerException;
use Symfony\Component\HttpClient\Exception\TransportException;
use Symfony\Component\HttpClient\NativeHttpClient;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\Test\TestHttpServer;

#[RequiresPhpExtension('zlib')]
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

    #[DataProvider('provideClients')]
    public function testDecompressionBombIsRejected(string $clientClass)
    {
        $response = $this->createClient($clientClass)->request('GET', $url = $this->url('/bomb'));

        $this->expectException(TransportException::class);
        $this->expectExceptionMessage(\sprintf('Content of "%s" inflated to more than 100 times its compressed size.', $url));

        $response->getContent();
    }

    #[DataProvider('provideClients')]
    public function testDecompressionBombIsRejectedWhileStreaming(string $clientClass)
    {
        $client = $this->createClient($clientClass);
        $response = $client->request('GET', $url = $this->url('/bomb'));
        $read = 0;

        try {
            foreach ($client->stream($response) as $chunk) {
                $read += \strlen($chunk->getContent());
            }
            $this->fail(TransportException::class.' expected');
        } catch (TransportException $e) {
            $this->assertSame(\sprintf('Content of "%s" inflated to more than 100 times its compressed size.', $url), $e->getMessage());
        }

        $this->assertLessThan(8 * 1024 * 1024, $read);
    }

    #[DataProvider('provideClients')]
    public function testDecompressionBombIsRejectedWhenTheResponseIsDiscarded(string $clientClass)
    {
        $client = $this->createClient($clientClass);
        $url = $this->url('/bomb-error');

        try {
            $client->request('GET', $url);
            $this->fail(ServerException::class.' expected');
        } catch (ServerException $e) {
            $this->assertSame(\sprintf('HTTP/1.1 500 Internal Server Error returned for "%s".', $url), $e->getMessage());
        }

        $this->expectException(TransportException::class);
        $this->expectExceptionMessage(\sprintf('Content of "%s" inflated to more than 100 times its compressed size.', $url));

        $e->getResponse()->getContent(false);
    }

    #[DataProvider('provideClients')]
    public function testLargeBodyWithAnOrdinaryRatioIsAccepted(string $clientClass)
    {
        $response = $this->createClient($clientClass)->request('GET', $this->url('/compressible'));
        $body = $response->getContent();

        $this->assertGreaterThan(2 * 1024 * 1024, \strlen($body));
        $this->assertCount(40000, json_decode($body, true));
    }

    #[DataProvider('provideClients')]
    public function testSmallBodyWithAHighRatioIsAccepted(string $clientClass)
    {
        $response = $this->createClient($clientClass)->request('GET', $this->url('/padded'));

        $this->assertSame(str_repeat(' ', 64 * 1024), $response->getContent());
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
