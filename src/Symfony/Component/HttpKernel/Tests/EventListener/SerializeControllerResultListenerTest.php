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
use Symfony\Component\HttpKernel\Attribute\Serialize;
use Symfony\Component\HttpKernel\Event\ControllerArgumentsEvent;
use Symfony\Component\HttpKernel\Event\ControllerArgumentsMetadata;
use Symfony\Component\HttpKernel\Event\ControllerAttributeEvent;
use Symfony\Component\HttpKernel\Event\ControllerEvent;
use Symfony\Component\HttpKernel\Event\ViewEvent;
use Symfony\Component\HttpKernel\EventListener\SerializeControllerResultAttributeListener;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\Serializer\SerializerInterface;

class SerializeControllerResultListenerTest extends TestCase
{
    public function testSerializeAttribute()
    {
        $controllerResult = new ProductCreated(10);
        $responseBody = '{"productId": 10}';

        $serializer = $this->createMock(SerializerInterface::class);
        $serializer->expects($this->once())
            ->method('serialize')
            ->with($controllerResult, 'json', ['foo' => 'bar'])
            ->willReturn($responseBody);

        $kernel = $this->createStub(HttpKernelInterface::class);
        $request = new Request();

        $controller = new GetApiController();
        $requestType = HttpKernelInterface::MAIN_REQUEST;
        $viewEvent = new ViewEvent(
            $kernel,
            $request,
            $requestType,
            $controllerResult,
            new ControllerArgumentsMetadata(
                new ControllerEvent($kernel, $controller, $request, $requestType),
                new ControllerArgumentsEvent(
                    $kernel,
                    $controller,
                    [],
                    $request,
                    $requestType,
                ),
            ),
        );

        $listener = new SerializeControllerResultAttributeListener($serializer);
        $listener->onView(new ControllerAttributeEvent(new Serialize(201, ['X-Test-Header' => 'abc'], ['foo' => 'bar']), $viewEvent));

        $response = $viewEvent->getResponse();

        self::assertSame(201, $response->getStatusCode());
        self::assertSame($responseBody, $response->getContent());
        self::assertSame('abc', $response->headers->get('X-Test-Header'));
    }
}

class ProductCreated
{
    public function __construct(public readonly int $productId)
    {
    }
}

class GetApiController
{
    #[Serialize(201, ['X-Test-Header' => 'abc'], ['foo' => 'bar'])]
    public function __invoke(): ProductCreated
    {
        return new ProductCreated(10);
    }
}
