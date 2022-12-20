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
use Symfony\Component\HttpFoundation\JsonResponse;

class JsonResponseTest extends TestCase
{
    public function testConstructorEmptyCreatesJsonObject()
    {
        $response = new JsonResponse();
        self::assertSame('{}', $response->getContent());
    }

    public function testConstructorWithArrayCreatesJsonArray()
    {
        $response = new JsonResponse([0, 1, 2, 3]);
        self::assertSame('[0,1,2,3]', $response->getContent());
    }

    public function testConstructorWithAssocArrayCreatesJsonObject()
    {
        $response = new JsonResponse(['foo' => 'bar']);
        self::assertSame('{"foo":"bar"}', $response->getContent());
    }

    public function testConstructorWithSimpleTypes()
    {
        $response = new JsonResponse('foo');
        self::assertSame('"foo"', $response->getContent());

        $response = new JsonResponse(0);
        self::assertSame('0', $response->getContent());

        $response = new JsonResponse(0.1);
        self::assertEquals(0.1, $response->getContent());
        self::assertIsString($response->getContent());

        $response = new JsonResponse(true);
        self::assertSame('true', $response->getContent());
    }

    public function testConstructorWithCustomStatus()
    {
        $response = new JsonResponse([], 202);
        self::assertSame(202, $response->getStatusCode());
    }

    public function testConstructorAddsContentTypeHeader()
    {
        $response = new JsonResponse();
        self::assertSame('application/json', $response->headers->get('Content-Type'));
    }

    public function testConstructorWithCustomHeaders()
    {
        $response = new JsonResponse([], 200, ['ETag' => 'foo']);
        self::assertSame('application/json', $response->headers->get('Content-Type'));
        self::assertSame('foo', $response->headers->get('ETag'));
    }

    public function testConstructorWithCustomContentType()
    {
        $headers = ['Content-Type' => 'application/vnd.acme.blog-v1+json'];

        $response = new JsonResponse([], 200, $headers);
        self::assertSame('application/vnd.acme.blog-v1+json', $response->headers->get('Content-Type'));
    }

    public function testSetJson()
    {
        $response = new JsonResponse('1', 200, [], true);
        self::assertEquals('1', $response->getContent());

        $response = new JsonResponse('[1]', 200, [], true);
        self::assertEquals('[1]', $response->getContent());

        $response = new JsonResponse(null, 200, []);
        $response->setJson('true');
        self::assertEquals('true', $response->getContent());
    }

    /**
     * @group legacy
     */
    public function testCreate()
    {
        $response = JsonResponse::create(['foo' => 'bar'], 204);

        self::assertInstanceOf(JsonResponse::class, $response);
        self::assertEquals('{"foo":"bar"}', $response->getContent());
        self::assertEquals(204, $response->getStatusCode());
    }

    /**
     * @group legacy
     */
    public function testStaticCreateEmptyJsonObject()
    {
        $response = JsonResponse::create();
        self::assertInstanceOf(JsonResponse::class, $response);
        self::assertSame('{}', $response->getContent());
    }

    /**
     * @group legacy
     */
    public function testStaticCreateJsonArray()
    {
        $response = JsonResponse::create([0, 1, 2, 3]);
        self::assertInstanceOf(JsonResponse::class, $response);
        self::assertSame('[0,1,2,3]', $response->getContent());
    }

    /**
     * @group legacy
     */
    public function testStaticCreateJsonObject()
    {
        $response = JsonResponse::create(['foo' => 'bar']);
        self::assertInstanceOf(JsonResponse::class, $response);
        self::assertSame('{"foo":"bar"}', $response->getContent());
    }

    /**
     * @group legacy
     */
    public function testStaticCreateWithSimpleTypes()
    {
        $response = JsonResponse::create('foo');
        self::assertInstanceOf(JsonResponse::class, $response);
        self::assertSame('"foo"', $response->getContent());

        $response = JsonResponse::create(0);
        self::assertInstanceOf(JsonResponse::class, $response);
        self::assertSame('0', $response->getContent());

        $response = JsonResponse::create(0.1);
        self::assertInstanceOf(JsonResponse::class, $response);
        self::assertEquals(0.1, $response->getContent());
        self::assertIsString($response->getContent());

        $response = JsonResponse::create(true);
        self::assertInstanceOf(JsonResponse::class, $response);
        self::assertSame('true', $response->getContent());
    }

    /**
     * @group legacy
     */
    public function testStaticCreateWithCustomStatus()
    {
        $response = JsonResponse::create([], 202);
        self::assertSame(202, $response->getStatusCode());
    }

    /**
     * @group legacy
     */
    public function testStaticCreateAddsContentTypeHeader()
    {
        $response = JsonResponse::create();
        self::assertSame('application/json', $response->headers->get('Content-Type'));
    }

    /**
     * @group legacy
     */
    public function testStaticCreateWithCustomHeaders()
    {
        $response = JsonResponse::create([], 200, ['ETag' => 'foo']);
        self::assertSame('application/json', $response->headers->get('Content-Type'));
        self::assertSame('foo', $response->headers->get('ETag'));
    }

    /**
     * @group legacy
     */
    public function testStaticCreateWithCustomContentType()
    {
        $headers = ['Content-Type' => 'application/vnd.acme.blog-v1+json'];

        $response = JsonResponse::create([], 200, $headers);
        self::assertSame('application/vnd.acme.blog-v1+json', $response->headers->get('Content-Type'));
    }

    public function testSetCallback()
    {
        $response = (new JsonResponse(['foo' => 'bar']))->setCallback('callback');

        self::assertEquals('/**/callback({"foo":"bar"});', $response->getContent());
        self::assertEquals('text/javascript', $response->headers->get('Content-Type'));
    }

    public function testJsonEncodeFlags()
    {
        $response = new JsonResponse('<>\'&"');

        self::assertEquals('"\u003C\u003E\u0027\u0026\u0022"', $response->getContent());
    }

    public function testGetEncodingOptions()
    {
        $response = new JsonResponse();

        self::assertEquals(\JSON_HEX_TAG | \JSON_HEX_APOS | \JSON_HEX_AMP | \JSON_HEX_QUOT, $response->getEncodingOptions());
    }

    public function testSetEncodingOptions()
    {
        $response = new JsonResponse();
        $response->setData([[1, 2, 3]]);

        self::assertEquals('[[1,2,3]]', $response->getContent());

        $response->setEncodingOptions(\JSON_FORCE_OBJECT);

        self::assertEquals('{"0":{"0":1,"1":2,"2":3}}', $response->getContent());
    }

    public function testItAcceptsJsonAsString()
    {
        $response = JsonResponse::fromJsonString('{"foo":"bar"}');
        self::assertSame('{"foo":"bar"}', $response->getContent());
    }

    public function testSetCallbackInvalidIdentifier()
    {
        self::expectException(\InvalidArgumentException::class);
        $response = new JsonResponse('foo');
        $response->setCallback('+invalid');
    }

    public function testSetContent()
    {
        self::expectException(\InvalidArgumentException::class);
        new JsonResponse("\xB1\x31");
    }

    public function testSetContentJsonSerializeError()
    {
        self::expectException(\Exception::class);
        self::expectExceptionMessage('This error is expected');

        $serializable = new JsonSerializableObject();

        new JsonResponse($serializable);
    }

    public function testSetComplexCallback()
    {
        $response = new JsonResponse(['foo' => 'bar']);
        $response->setCallback('ಠ_ಠ["foo"].bar[0]');

        self::assertEquals('/**/ಠ_ಠ["foo"].bar[0]({"foo":"bar"});', $response->getContent());
    }

    public function testConstructorWithNullAsDataThrowsAnUnexpectedValueException()
    {
        self::expectException(\TypeError::class);
        self::expectExceptionMessage('If $json is set to true, argument $data must be a string or object implementing __toString(), "null" given.');

        new JsonResponse(null, 200, [], true);
    }

    public function testConstructorWithObjectWithToStringMethod()
    {
        $class = new class() {
            public function __toString(): string
            {
                return '{}';
            }
        };

        $response = new JsonResponse($class, 200, [], true);

        self::assertSame('{}', $response->getContent());
    }

    public function testConstructorWithObjectWithoutToStringMethodThrowsAnException()
    {
        self::expectException(\TypeError::class);
        self::expectExceptionMessage('If $json is set to true, argument $data must be a string or object implementing __toString(), "stdClass" given.');

        new JsonResponse(new \stdClass(), 200, [], true);
    }
}

class JsonSerializableObject implements \JsonSerializable
{
    public function jsonSerialize(): array
    {
        throw new \Exception('This error is expected');
    }
}
