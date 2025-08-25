<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HttpKernel\Tests\EventListener;

use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ControllerArgumentsEvent;
use Symfony\Component\HttpKernel\Event\ViewEvent;
use Symfony\Component\HttpKernel\EventListener\SerializeResponseListener;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\HttpKernel\Tests\Fixtures\Controller\SerializeResponseController;
use Symfony\Component\Serializer\Encoder\CsvEncoder;
use Symfony\Component\Serializer\Encoder\JsonEncoder;
use Symfony\Component\Serializer\Encoder\XmlEncoder;
use Symfony\Component\Serializer\Encoder\YamlEncoder;
use Symfony\Component\Serializer\Normalizer\ObjectNormalizer;
use Symfony\Component\Serializer\Serializer;

class SerializeResponseListenerTest extends TestCase
{
    private SerializeResponseListener $listener;
    private Serializer $serializer;

    protected function setUp(): void
    {
        $this->serializer = new Serializer(
            [new ObjectNormalizer()],
            [new JsonEncoder(), new XmlEncoder(), new YamlEncoder(), new CsvEncoder()]
        );
        $this->listener = new SerializeResponseListener($this->serializer);
    }

    public function testSerializesControllerResultToJson()
    {
        $data = ['message' => 'Hello World', 'status' => 'success'];

        $request = new Request();
        $kernel = $this->createMock(HttpKernelInterface::class);
        $controllerArgumentsEvent = new ControllerArgumentsEvent($kernel, [new SerializeResponseController(), 'createWithCustomSettings'], [], $request, null);

        $event = new ViewEvent($kernel, $request, HttpKernelInterface::MAIN_REQUEST, $data, $controllerArgumentsEvent);

        $this->listener->onKernelView($event);

        $response = $event->getResponse();
        $this->assertInstanceOf(Response::class, $response);
        $this->assertSame(201, $response->getStatusCode());
        $this->assertSame('value', $response->headers->get('X-Custom'));
        $this->assertSame('application/json', $response->headers->get('Content-Type'));
        $this->assertSame('{"message":"Hello World","status":"success"}', $response->getContent());
    }

    public function testUsesDefaultStatusAndHeaders()
    {
        $data = ['test' => true];

        $request = new Request();
        $kernel = $this->createMock(HttpKernelInterface::class);
        $controllerArgumentsEvent = new ControllerArgumentsEvent($kernel, [new SerializeResponseController(), 'createWithDefaults'], [], $request, null);

        $event = new ViewEvent($kernel, $request, HttpKernelInterface::MAIN_REQUEST, $data, $controllerArgumentsEvent);

        $this->listener->onKernelView($event);

        $response = $event->getResponse();
        $this->assertInstanceOf(Response::class, $response);
        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('application/json', $response->headers->get('Content-Type'));
        $this->assertSame('{"test":true}', $response->getContent());
    }

    public function testDoesNotProcessWhenNoAttributePresent()
    {
        $data = ['test' => true];

        $request = new Request();
        $kernel = $this->createMock(HttpKernelInterface::class);
        $controllerArgumentsEvent = new ControllerArgumentsEvent($kernel, [new SerializeResponseController(), 'createWithoutAttribute'], [], $request, null);

        $event = new ViewEvent($kernel, $request, HttpKernelInterface::MAIN_REQUEST, $data, $controllerArgumentsEvent);

        $this->listener->onKernelView($event);

        $this->assertNull($event->getResponse());
    }

    public function testDoesNotProcessWhenNoControllerArgumentsEvent()
    {
        $data = ['test' => true];

        $request = new Request();
        $kernel = $this->createMock(HttpKernelInterface::class);

        $event = new ViewEvent($kernel, $request, HttpKernelInterface::MAIN_REQUEST, $data);

        $this->listener->onKernelView($event);

        $this->assertNull($event->getResponse());
    }

    public function testHandlesNullControllerResult()
    {
        $request = new Request();
        $kernel = $this->createMock(HttpKernelInterface::class);
        $controllerArgumentsEvent = new ControllerArgumentsEvent($kernel, [new SerializeResponseController(), 'createWithDefaults'], [], $request, null);

        $event = new ViewEvent($kernel, $request, HttpKernelInterface::MAIN_REQUEST, null, $controllerArgumentsEvent);

        $this->listener->onKernelView($event);

        $response = $event->getResponse();
        $this->assertInstanceOf(Response::class, $response);
        $this->assertSame('application/json', $response->headers->get('Content-Type'));
        $this->assertSame('null', $response->getContent());
    }

    public function testSerializationContextIsUsed()
    {
        $data = new class {
            public string $public = 'visible';
            private string $private = 'hidden';
        };

        $request = new Request();
        $kernel = $this->createMock(HttpKernelInterface::class);
        $controllerArgumentsEvent = new ControllerArgumentsEvent($kernel, [new SerializeResponseController(), 'createWithSerializationContext'], [], $request, null);

        $event = new ViewEvent($kernel, $request, HttpKernelInterface::MAIN_REQUEST, $data, $controllerArgumentsEvent);

        $this->listener->onKernelView($event);

        $response = $event->getResponse();
        $content = $response->getContent();
        $this->assertStringContainsString('visible', $content);
        $this->assertStringNotContainsString('hidden', $content);
    }

    public function testSerializesToXmlFormat()
    {
        $data = ['message' => 'Hello World', 'status' => 'success'];

        $request = new Request();
        $kernel = $this->createMock(HttpKernelInterface::class);
        $controllerArgumentsEvent = new ControllerArgumentsEvent($kernel, [new SerializeResponseController(), 'createWithXmlFormat'], [], $request, null);

        $event = new ViewEvent($kernel, $request, HttpKernelInterface::MAIN_REQUEST, $data, $controllerArgumentsEvent);

        $this->listener->onKernelView($event);

        $response = $event->getResponse();
        $this->assertInstanceOf(Response::class, $response);
        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('application/xml', $response->headers->get('Content-Type'));
        $this->assertStringContainsString('<?xml', $response->getContent());
    }

    public function testSerializesToYamlFormat()
    {
        $data = ['message' => 'Hello World', 'status' => 'success'];

        $request = new Request();
        $kernel = $this->createMock(HttpKernelInterface::class);
        $controllerArgumentsEvent = new ControllerArgumentsEvent($kernel, [new SerializeResponseController(), 'createWithYamlFormat'], [], $request, null);

        $event = new ViewEvent($kernel, $request, HttpKernelInterface::MAIN_REQUEST, $data, $controllerArgumentsEvent);

        $this->listener->onKernelView($event);

        $response = $event->getResponse();
        $this->assertInstanceOf(Response::class, $response);
        $this->assertSame(202, $response->getStatusCode());
        $this->assertSame('application/x-yaml', $response->headers->get('Content-Type'));
        $this->assertStringContainsString('message: \'Hello World\'', $response->getContent());
    }

    public function testSerializesToCsvFormat()
    {
        $data = [
            ['name' => 'John', 'age' => 30],
            ['name' => 'Jane', 'age' => 25],
        ];

        $request = new Request();
        $kernel = $this->createMock(HttpKernelInterface::class);
        $controllerArgumentsEvent = new ControllerArgumentsEvent($kernel, [new SerializeResponseController(), 'createWithCsvFormat'], [], $request, null);

        $event = new ViewEvent($kernel, $request, HttpKernelInterface::MAIN_REQUEST, $data, $controllerArgumentsEvent);

        $this->listener->onKernelView($event);

        $response = $event->getResponse();
        $this->assertInstanceOf(Response::class, $response);
        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('text/csv', $response->headers->get('Content-Type'));
        $this->assertStringContainsString('name,age', $response->getContent());
    }

    public function testRespectsCustomContentTypeHeader()
    {
        $data = ['message' => 'Hello World', 'status' => 'success'];

        $request = new Request();
        $kernel = $this->createMock(HttpKernelInterface::class);
        $controllerArgumentsEvent = new ControllerArgumentsEvent($kernel, [new SerializeResponseController(), 'createWithCustomContentType'], [], $request, null);

        $event = new ViewEvent($kernel, $request, HttpKernelInterface::MAIN_REQUEST, $data, $controllerArgumentsEvent);

        $this->listener->onKernelView($event);

        $response = $event->getResponse();
        $this->assertInstanceOf(Response::class, $response);
        $this->assertSame(200, $response->getStatusCode());
        // Should use the custom content type, not override with application/json
        $this->assertSame('application/vnd.api+json', $response->headers->get('Content-Type'));
        $this->assertSame('{"message":"Hello World","status":"success"}', $response->getContent());
    }
}
