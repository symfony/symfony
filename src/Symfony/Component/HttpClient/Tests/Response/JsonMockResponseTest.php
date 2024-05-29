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
use Symfony\Component\HttpClient\Exception\InvalidArgumentException;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\JsonMockResponse;

final class JsonMockResponseTest extends TestCase
{
    public function testDefaults()
    {
        $client = new MockHttpClient(new JsonMockResponse());
        $response = $client->request('GET', 'https://symfony.com');

        $this->assertSame([], $response->toArray());
        $this->assertSame('application/json', $response->getHeaders()['content-type'][0]);
    }

    public function testInvalidBody()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('JSON encoding failed: Malformed UTF-8 characters, possibly incorrectly encoded');

        new JsonMockResponse("\xB1\x31");
    }

    public function testJsonEncodeArray()
    {
        $client = new MockHttpClient(new JsonMockResponse([
            'foo' => 'bar',
            'ccc' => 123,
        ]));
        $response = $client->request('GET', 'https://symfony.com');

        $this->assertSame([
            'foo' => 'bar',
            'ccc' => 123,
        ], $response->toArray());
        $this->assertSame('application/json', $response->getHeaders()['content-type'][0]);
    }

    public function testJsonEncodeString()
    {
        $client = new MockHttpClient(new JsonMockResponse('foobarccc'));
        $response = $client->request('GET', 'https://symfony.com');

        $this->assertSame('"foobarccc"', $response->getContent());
        $this->assertSame('application/json', $response->getHeaders()['content-type'][0]);
    }

    public function testJsonEncodeFloat()
    {
        $client = new MockHttpClient(new JsonMockResponse([
            'foo' => 1.23,
            'ccc' => 1.0,
            'baz' => 10.,
        ]));
        $response = $client->request('GET', 'https://symfony.com');

        $this->assertSame([
            'foo' => 1.23,
            'ccc' => 1.,
            'baz' => 10.,
        ], $response->toArray());
    }

    /**
     * @dataProvider responseHeadersProvider
     */
    public function testResponseHeaders(string $expectedContentType, array $responseHeaders)
    {
        $client = new MockHttpClient(new JsonMockResponse([
            'foo' => 'bar',
        ], [
            'response_headers' => $responseHeaders,
            'http_code' => 201,
        ]));
        $response = $client->request('GET', 'https://symfony.com');

        $this->assertSame($expectedContentType, $response->getHeaders()['content-type'][0]);
        $this->assertSame(201, $response->getStatusCode());
    }

    public static function responseHeadersProvider(): array
    {
        return [
            ['application/json', []],
            ['application/json', ['x-foo' => 'ccc']],
            ['application/problem+json', ['content-type' => 'application/problem+json']],
            ['application/problem+json', ['x-foo' => 'ccc', 'content-type' => 'application/problem+json']],
        ];
    }

    public function testFromFile()
    {
        $client = new MockHttpClient(JsonMockResponse::fromFile(__DIR__.'/Fixtures/response.json'));
        $response = $client->request('GET', 'https://symfony.com');

        $this->assertSame([
            'foo' => 'bar',
        ], $response->toArray());
        $this->assertSame('application/json', $response->getHeaders()['content-type'][0]);
    }

    public function testFromFileWithInvalidJson()
    {
        $path = __DIR__.'/Fixtures/invalid_json.json';

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage(sprintf('File "%s" does not contain valid JSON.', $path));

        JsonMockResponse::fromFile($path);
    }
}
