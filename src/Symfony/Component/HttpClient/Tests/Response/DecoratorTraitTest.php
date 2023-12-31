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
use Symfony\Component\HttpClient\Response\DecoratorTrait;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Component\HttpClient\Response\ResponseStream;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * @author Cyril Vermande <https://github.com/cyve>
 */
class DecoratorTraitTest extends TestCase
{
    private $subject;

    protected function setUp(): void
    {
        $decorated = MockResponse::fromRequest(
            'GET',
            'https://symfony.com',
            [],
            new MockResponse(
                '{"foo":"bar"}',
                [
                    'http_code' => 200,
                    'response_headers' => ['Content-Type' => 'application/json'],
                ],
            )
        );
        $this->subject = $this->getObjectForTrait(DecoratorTrait::class, [$decorated]);
    }

    public function testShouldReturnDecoratedStatusCode()
    {
        $this->assertEquals(200, $this->subject->getStatusCode());
    }

    public function testShouldReturnDecoratedHeaders()
    {
        $this->assertEquals(['content-type' => ['application/json']], $this->subject->getHeaders());
    }

    public function testShouldReturnDecoratedContent()
    {
        $this->assertEquals('{"foo":"bar"}', $this->subject->getContent());
    }

    public function testShouldReturnDecoratedContentInArray()
    {
        $this->assertEquals(['foo' => 'bar'], $this->subject->toArray());
    }

    public function testShouldReturnDecoratedInfo()
    {
        $info = $this->subject->getInfo();
        $this->assertArrayHasKey('http_code', $info);
        $this->assertArrayHasKey('response_headers', $info);
        $this->assertArrayHasKey('http_method', $info);
        $this->assertArrayHasKey('url', $info);
    }

    public function testShouldReturnDecoratedInfoByType()
    {
        $this->assertEquals('https://symfony.com', $this->subject->getInfo('url'));
    }

    public function testShouldReturnDecoratedResponseAsStream()
    {
        $stream = $this->subject->toStream();
        $this->assertTrue(\is_resource($stream));
        $this->assertEquals('{"foo":"bar"}', stream_get_contents($stream));
    }

    public function testShouldStreamTheDecoratedResponses()
    {
        $httpClient = $this->createMock(HttpClientInterface::class);
        $httpClient->method('stream')->willReturnCallback(fn ($responses, $timeout) => new ResponseStream(MockResponse::stream($responses, $timeout)));

        foreach ($this->subject::stream($httpClient, [$this->subject]) as $response => $chunk) {
            // Assert that the "stream()" method yield chunks from the decorated response, but with the wrapped response as key.
            $this->assertSame($response, $this->subject);
            break;
        }
    }
}
