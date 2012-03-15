<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Tests\Component\HttpFoundation;

use Symfony\Component\HttpFoundation\JsonResponse;

/**
 * @covers Symfony\Component\HttpFoundation\JsonResponse::__construct
 */
class JsonResponseTest extends \PHPUnit_Framework_TestCase
{
    public function testConstructorEmptyCreatesJsonObject()
    {
        $response = new JsonResponse();
        $this->assertSame('{}', $response->getContent());
    }

    public function testConstructorWithArrayCreatesJsonArray()
    {
        $response = new JsonResponse(array(0, 1, 2, 3));
        $this->assertSame('[0,1,2,3]', $response->getContent());
    }

    public function testConstructorWithAssocArrayCreatesJsonObject()
    {
        $response = new JsonResponse(array('foo' => 'bar'));
        $this->assertSame('{"foo":"bar"}', $response->getContent());
    }

    public function testConstructorWithSimpleTypes()
    {
        $response = new JsonResponse('foo');
        $this->assertSame('"foo"', $response->getContent());

        $response = new JsonResponse(0);
        $this->assertSame('0', $response->getContent());

        $response = new JsonResponse(0.1);
        $this->assertSame('0.1', $response->getContent());

        $response = new JsonResponse(true);
        $this->assertSame('true', $response->getContent());
    }

    public function testConstructorWithCustomStatus()
    {
        $response = new JsonResponse(array(), 202);
        $this->assertSame(202, $response->getStatusCode());
    }

    public function testConstructorAddsContentTypeHeader()
    {
        $response = new JsonResponse();
        $this->assertSame('application/json', $response->headers->get('Content-Type'));
    }

    public function testConstructorWithCustomHeaders()
    {
        $response = new JsonResponse(array(), 200, array('ETag' => 'foo'));
        $this->assertSame('application/json', $response->headers->get('Content-Type'));
        $this->assertSame('foo', $response->headers->get('ETag'));
    }

    public function testConstructorWithCustomContentType()
    {
        $headers = array('Content-Type' => 'application/vnd.acme.blog-v1+json');

        $response = new JsonResponse(array(), 200, $headers);
        $this->assertSame('application/vnd.acme.blog-v1+json', $response->headers->get('Content-Type'));
    }

    public function testCreate()
    {
        $response = JsonResponse::create(array('foo' => 'bar'), 204);

        $this->assertInstanceOf('Symfony\Component\HttpFoundation\JsonResponse', $response);
        $this->assertEquals('{"foo":"bar"}', $response->getContent());
        $this->assertEquals(204, $response->getStatusCode());
    }
}
