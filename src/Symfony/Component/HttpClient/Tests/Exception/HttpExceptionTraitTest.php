<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HttpClient\Tests\Exception;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\Exception\ClientException;
use Symfony\Component\HttpClient\Exception\HttpExceptionTrait;
use Symfony\Component\HttpClient\Exception\TransportException;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Contracts\HttpClient\ResponseInterface;

/**
 * @author Kévin Dunglas <dunglas@gmail.com>
 */
class HttpExceptionTraitTest extends TestCase
{
    public static function provideParseError(): iterable
    {
        $errorWithoutMessage = 'HTTP/1.1 400 Bad Request returned for "http://example.com".';

        $errorWithMessage = <<<ERROR
            An error occurred

            Some details
            ERROR;

        yield ['application/ld+json', '{"hydra:title": "An error occurred", "hydra:description": "Some details"}', $errorWithMessage];
        yield ['application/problem+json', '{"title": "An error occurred", "detail": "Some details"}', $errorWithMessage];
        yield ['application/vnd.api+json', '{"title": "An error occurred", "detail": "Some details"}', $errorWithMessage];
        yield ['application/json', '{"title": "An error occurred", "detail": {"field_name": ["Some details"]}}', $errorWithoutMessage];
    }

    #[DataProvider('provideParseError')]
    public function testParseError(string $mimeType, string $json, string $expectedMessage)
    {
        $response = $this->createStub(ResponseInterface::class);
        $response
            ->method('getInfo')
            ->willReturnMap([
                ['http_code', 400],
                ['url', 'http://example.com'],
                ['response_headers', [
                    'HTTP/1.1 400 Bad Request',
                    'Content-Type: '.$mimeType,
                ]],
            ]);
        $response->method('getContent')->willReturn($json);

        $e = new TestException($response);
        $this->assertSame(400, $e->getCode());
        $this->assertSame($expectedMessage, $e->getMessage());
    }

    public function testParseErrorOnAStreamedResponse()
    {
        $e = $this->requestError('{"title": "An error occurred", "detail": "Some details"}');

        $this->assertSame("An error occurred\n\nSome details", $e->getMessage());
    }

    public function testParseErrorKeepsTheHeadOfTheBodyOnly()
    {
        $e = $this->requestError($this->longErrorBody());

        $this->assertSame('HTTP/1.1 400 Bad Request returned for "http://example.com/".', $e->getMessage());
    }

    public function testBufferedResponseStaysReadable()
    {
        $e = $this->requestError($this->longErrorBody());

        $this->assertSame($this->longErrorBody(), $e->getResponse()->getContent(false));
    }

    public function testUnbufferedResponseStaysUnreadable()
    {
        $e = $this->requestError($this->longErrorBody(), ['buffer' => false]);

        $this->expectException(TransportException::class);
        $this->expectExceptionMessage('Cannot get the content of the response twice: buffering is disabled.');

        $e->getResponse()->getContent(false);
    }

    public function testNonJsonResponseIsNotRead()
    {
        $e = $this->requestError($this->longErrorBody(), ['buffer' => false], 'text/plain');

        $this->assertSame($this->longErrorBody(), $e->getResponse()->getContent(false));
    }

    private function longErrorBody(): string
    {
        return json_encode(['title' => 'An error occurred', 'detail' => str_repeat('x', 64 * 1024)]);
    }

    private function requestError(string $body, array $options = [], string $contentType = 'application/problem+json'): ClientException
    {
        $client = new MockHttpClient(new MockResponse(str_split($body, 16384), [
            'http_code' => 400,
            'response_headers' => ['HTTP/1.1 400 Bad Request', 'Content-Type: '.$contentType],
        ]));

        try {
            $client->request('GET', 'http://example.com/', $options)->getHeaders();
        } catch (ClientException $e) {
            return $e;
        }

        $this->fail(ClientException::class.' expected');
    }
}

class TestException extends \Exception
{
    use HttpExceptionTrait;
}
