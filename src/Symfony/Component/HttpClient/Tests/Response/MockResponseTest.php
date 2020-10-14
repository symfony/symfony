<?php

namespace Symfony\Component\HttpClient\Tests\Response;

use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\Exception\JsonException;
use Symfony\Component\HttpClient\Response\MockResponse;

/**
 * Test methods from Symfony\Component\HttpClient\Response\*ResponseTrait.
 */
class MockResponseTest extends TestCase
{
    public function testToArray()
    {
        $data = ['color' => 'orange', 'size' => 42];
        $response = new MockResponse(json_encode($data));
        $response = MockResponse::fromRequest('GET', 'https://example.com/file.json', [], $response);

        $this->assertSame($data, $response->toArray());
    }

    /**
     * @dataProvider toArrayErrors
     */
    public function testToArrayError($content, $responseHeaders, $message)
    {
        $this->expectException(JsonException::class);
        $this->expectExceptionMessage($message);

        $response = new MockResponse($content, ['response_headers' => $responseHeaders]);
        $response = MockResponse::fromRequest('GET', 'https://example.com/file.json', [], $response);
        $response->toArray();
    }

    public function testUrlHttpMethodMockResponse(): void
    {
        $responseMock = new MockResponse(json_encode(['foo' => 'bar']));
        $url = 'https://example.com/some-endpoint';
        $response = MockResponse::fromRequest('GET', $url, [], $responseMock);

        $this->assertSame('GET', $response->getInfo('http_method'));
        $this->assertSame('GET', $responseMock->getRequestMethod());

        $this->assertSame($url, $response->getInfo('url'));
        $this->assertSame($url, $responseMock->getRequestUrl());
    }

    public function toArrayErrors()
    {
        yield [
            'content' => '',
            'responseHeaders' => [],
            'message' => 'Response body is empty.',
        ];

        yield [
            'content' => 'not json',
            'responseHeaders' => [],
            'message' => 'Syntax error for "https://example.com/file.json".',
        ];

        yield [
            'content' => '[1,2}',
            'responseHeaders' => [],
            'message' => 'State mismatch (invalid or malformed JSON) for "https://example.com/file.json".',
        ];

        yield [
            'content' => '"not an array"',
            'responseHeaders' => [],
            'message' => 'JSON content was expected to decode to an array, "string" returned for "https://example.com/file.json".',
        ];

        yield [
            'content' => '8',
            'responseHeaders' => [],
            'message' => 'JSON content was expected to decode to an array, "int" returned for "https://example.com/file.json".',
        ];
    }
}
