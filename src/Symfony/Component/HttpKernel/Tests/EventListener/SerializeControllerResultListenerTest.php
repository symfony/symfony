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
use Symfony\Component\HttpKernel\Event\ViewEvent;
use Symfony\Component\HttpKernel\EventListener\SerializeControllerResultListener;
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
            ->with($controllerResult)
            ->willReturn($responseBody);

        $httpKernel = $this->createMock(HttpKernelInterface::class);
        $request = new Request();

        $event = new ViewEvent(
            $httpKernel,
            $request,
            HttpKernelInterface::MAIN_REQUEST,
            $controllerResult,
            new ControllerArgumentsEvent(
                $httpKernel,
                new GetApiController(),
                [],
                $request,
                HttpKernelInterface::MAIN_REQUEST,
            ),
        );

        $listener = new SerializeControllerResultListener($serializer);
        $listener->onView($event);

        $response = $event->getResponse();

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
    #[Serialize(201, ['X-Test-Header' => 'abc'])]
    public function __invoke(): ProductCreated
    {
        return new ProductCreated(10);
    }
}
