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

use Symfony\Component\HttpFoundation\FileStreamResponse;

class FileStreamResponseTest extends \PHPUnit_Framework_TestCase
{
    public function testConstructor()
    {
        $fd = fopen("php://stdin", "r");
        $response = new FileStreamResponse($fd, 404, array("Content-Type" => "text/plain"));

        $this->assertEquals(404, $response->getStatusCode());
        $this->assertEquals("text/plain", $response->headers->get('Content-Type'));
    }

    public function testCreate()
    {
        $fd = fopen("php://stdin", "r");
        $response = FileStreamResponse::create($fd, 404, array("Content-Type" => "text/plain"));

        $this->assertEquals(404, $response->getStatusCode());
        $this->assertEquals("text/plain", $response->headers->get('Content-Type'));
    }

    /**
     * @expectedException \LogicException
     */
    public function testSetHandleNonResource()
    {
        $response = new FileStreamResponse();

        $response->setHandle("non resource");
    }

    public function testSendContent()
    {
        $fd = fopen("data:text/plain;base64,U3ltZm9ueTIgaXMgZ3JlYXQ=", "r");
        $response = new FileStreamResponse($fd);

        $this->expectOutputString("Symfony2 is great");

        $response->sendContent();
        $response->sendContent();
    }

    /**
     * @expectedException \LogicException
     */
    public function testSendContentNonResource()
    {
        $response = new FileStreamResponse();
        $response->sendContent();
    }

    /**
     * @expectedException \LogicException
     */
    public function testSetContent()
    {
        $response = new FileStreamResponse();
        $response->setContent("test");
    }

    public function testGetContent()
    {
        $response = new FileStreamResponse();

        $this->assertFalse($response->getContent());
    }
}
