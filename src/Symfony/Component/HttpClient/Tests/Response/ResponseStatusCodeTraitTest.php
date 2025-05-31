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
use Symfony\Component\HttpClient\Response\MockResponse;

class ResponseStatusCodeTraitTest extends TestCase
{
    public function testIsOk()
    {
        $response = self::createResponse(200);
        $this->assertTrue($response->isOk());
    }

    public function testIsCreated()
    {
        $response = self::createResponse(201);
        $this->assertTrue($response->isCreated());
    }

    public function testIsAccepted()
    {
        $response = self::createResponse(202);
        $this->assertTrue($response->isAccepted());
    }

    public function testIsNoContent()
    {
        $response = self::createEmptyContentResponse();
        $this->assertTrue($response->isNoContent());

        $response = self::createEmptyContentResponse(204, json_encode(['foo' => 'bar', 'bar' => 'baz']));
        $this->assertFalse($response->isNoContent());

        $fakeStatusCode = 999;
        $response = self::createEmptyContentResponse($fakeStatusCode);
        $this->assertTrue($response->isNoContent($fakeStatusCode));

        $response = self::createEmptyContentResponse($fakeStatusCode);
        // we don't pass any status code, so the default status code (204) is used
        $this->assertFalse($response->isNoContent());
    }

    public function testIsMovedPermanently()
    {
        $response = self::createResponse(301);
        $this->assertTrue($response->isMovedPermanently());
    }

    public function testIsFound()
    {
        $response = self::createResponse(302);
        $this->assertTrue($response->isFound());
    }

    public function testIsNotModified()
    {
        $response = self::createResponse(304);
        $this->assertTrue($response->isNotModified());
    }

    public function testIsBadRequest()
    {
        $response = self::createResponse(400);
        $this->assertTrue($response->isBadRequest());
    }

    public function testIsUnauthorized()
    {
        $response = self::createResponse(401);
        $this->assertTrue($response->isUnauthorized());
    }

    public function testIsPaymentRequired()
    {
        $response = self::createResponse(402);
        $this->assertTrue($response->isPaymentRequired());
    }

    public function testIsForbidden()
    {
        $response = self::createResponse(403);
        $this->assertTrue($response->isForbidden());
    }

    public function testIsNotFound()
    {
        $response = self::createResponse(404);
        $this->assertTrue($response->isNotFound());
    }

    public function testIsMethodNotAllowed()
    {
        $response = self::createResponse(405);
        $this->assertTrue($response->isMethodNotAllowed());
    }

    public function testIsNotAcceptable()
    {
        $response = self::createResponse(406);
        $this->assertTrue($response->isNotAcceptable());
    }

    public function testIsRequestTimeout()
    {
        $response = self::createResponse(408);
        $this->assertTrue($response->isRequestTimeout());
    }

    public function testIsConflict()
    {
        $response = self::createResponse(409);
        $this->assertTrue($response->isConflict());
    }

    public function testIsGone()
    {
        $response = self::createResponse(410);
        $this->assertTrue($response->isGone());
    }

    public function testIsUnprocessableEntity()
    {
        $response = self::createResponse(422);
        $this->assertTrue($response->isUnprocessableEntity());
    }

    public function testIsTooManyRequests()
    {
        $response = self::createResponse(429);
        $this->assertTrue($response->isTooManyRequests());
    }

    private static function createResponse(int $statusCode, array $content = ['foo' => 'bar']): MockResponse
    {
        $responseMock = new MockResponse(json_encode($content), [
            'http_code' => $statusCode,
        ]);

        $response = MockResponse::fromRequest('GET', 'https://example.com/some-endpoint', [], $responseMock);
        $response->toArray(false);

        return $response;
    }

    private static function createEmptyContentResponse(int $statusCode = 204, string $content = ''): MockResponse
    {
        $responseMock = new MockResponse($content, [
            'http_code' => $statusCode,
        ]);

        $response = MockResponse::fromRequest('GET', 'https://example.com/some-endpoint', [], $responseMock);
        $response->getContent(false);

        return $response;
    }
}
