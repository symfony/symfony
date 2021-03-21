<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bridge\Monolog\Tests\Processor;

use PHPUnit\Framework\TestCase;
use Symfony\Bridge\Monolog\Processor\RouteProcessor;
use Symfony\Component\HttpFoundation\ParameterBag;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\FinishRequestEvent;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;

class RouteProcessorTest extends TestCase
{
    private const TEST_CONTROLLER = 'App\Controller\SomeController::someMethod';
    private const TEST_ROUTE = 'someRouteName';
    private const TEST_PARAMS = ['param1' => 'value1'];

    public function testProcessor()
    {
        $request = $this->mockFilledRequest();
        $processor = new RouteProcessor();
        $processor->addRouteData($this->getRequestEvent($request));

        $record = $processor(['extra' => []]);

        $this->assertArrayHasKey('requests', $record['extra']);
        $this->assertCount(1, $record['extra']['requests']);
        $this->assertEquals(
            ['controller' => self::TEST_CONTROLLER, 'route' => self::TEST_ROUTE, 'route_params' => self::TEST_PARAMS],
            $record['extra']['requests'][0]
        );
    }

    public function testProcessorWithoutParams()
    {
        $request = $this->mockFilledRequest();
        $processor = new RouteProcessor(false);
        $processor->addRouteData($this->getRequestEvent($request));

        $record = $processor(['extra' => []]);

        $this->assertArrayHasKey('requests', $record['extra']);
        $this->assertCount(1, $record['extra']['requests']);
        $this->assertEquals(
            ['controller' => self::TEST_CONTROLLER, 'route' => self::TEST_ROUTE],
            $record['extra']['requests'][0]
        );
    }

    public function testProcessorWithSubRequests()
    {
        $controllerFromSubRequest = 'OtherController::otherMethod';
        $mainRequest = $this->mockFilledRequest();
        $subRequest = $this->mockFilledRequest($controllerFromSubRequest);

        $processor = new RouteProcessor(false);
        $processor->addRouteData($this->getRequestEvent($mainRequest));
        $processor->addRouteData($this->getRequestEvent($subRequest, HttpKernelInterface::SUB_REQUEST));

        $record = $processor(['extra' => []]);

        $this->assertArrayHasKey('requests', $record['extra']);
        $this->assertCount(2, $record['extra']['requests']);
        $this->assertEquals(
            ['controller' => self::TEST_CONTROLLER, 'route' => self::TEST_ROUTE],
            $record['extra']['requests'][0]
        );
        $this->assertEquals(
            ['controller' => $controllerFromSubRequest, 'route' => self::TEST_ROUTE],
            $record['extra']['requests'][1]
        );
    }

    public function testFinishRequestRemovesRelatedEntry()
    {
        $mainRequest = $this->mockFilledRequest();
        $subRequest = $this->mockFilledRequest('OtherController::otherMethod');

        $processor = new RouteProcessor(false);
        $processor->addRouteData($this->getRequestEvent($mainRequest));
        $processor->addRouteData($this->getRequestEvent($subRequest, HttpKernelInterface::SUB_REQUEST));
        $processor->removeRouteData($this->getFinishRequestEvent($subRequest));
        $record = $processor(['extra' => []]);

        $this->assertArrayHasKey('requests', $record['extra']);
        $this->assertCount(1, $record['extra']['requests']);
        $this->assertEquals(
            ['controller' => self::TEST_CONTROLLER, 'route' => self::TEST_ROUTE],
            $record['extra']['requests'][0]
        );

        $processor->removeRouteData($this->getFinishRequestEvent($mainRequest));
        $record = $processor(['extra' => []]);

        $this->assertArrayNotHasKey('requests', $record['extra']);
    }

    public function testProcessorWithEmptyRequest()
    {
        $request = $this->mockEmptyRequest();
        $processor = new RouteProcessor();
        $processor->addRouteData($this->getRequestEvent($request));

        $record = $processor(['extra' => []]);
        $this->assertEquals(['extra' => []], $record);
    }

    public function testProcessorDoesNothingWhenNoRequest()
    {
        $processor = new RouteProcessor();

        $record = $processor(['extra' => []]);
        $this->assertEquals(['extra' => []], $record);
    }

    private function getRequestEvent(Request $request, int $requestType = HttpKernelInterface::MAIN_REQUEST): RequestEvent
    {
        return new RequestEvent($this->createMock(HttpKernelInterface::class), $request, $requestType);
    }

    private function getFinishRequestEvent(Request $request): FinishRequestEvent
    {
        return new FinishRequestEvent($this->createMock(HttpKernelInterface::class), $request, HttpKernelInterface::MAIN_REQUEST);
    }

    private function mockEmptyRequest(): Request
    {
        return $this->mockRequest([]);
    }

    private function mockFilledRequest(string $controller = self::TEST_CONTROLLER): Request
    {
        return $this->mockRequest([
            '_controller' => $controller,
            '_route' => self::TEST_ROUTE,
            '_route_params' => self::TEST_PARAMS,
        ]);
    }

    private function mockRequest(array $attributes): Request
    {
        $request = $this->createMock(Request::class);
        $request->attributes = new ParameterBag($attributes);

        return $request;
    }
}
