<?php

namespace Symfony\Component\HttpKernel\Tests\EventListener;

use Doctrine\Common\Annotations\AnnotationReader;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\ControllerEvent;
use Symfony\Component\HttpKernel\EventListener\ControllerListener;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\HttpKernel\Tests\Fixtures\Controller\AnnotatedController;
use Symfony\Component\HttpKernel\Tests\Fixtures\KernelForTest;

class ControllerListenerTest extends TestCase
{
    private $listener;
    private $reader;

    protected function setUp(): void
    {
        $this->reader = new AnnotationReader();
        $this->listener = new ControllerListener($this->reader);
    }

    public function testOnController()
    {
        $event = $this->createControllerEvent([new AnnotatedController(), 'queryParam']);
        $request = $event->getRequest();

        $this->listener->onController($event);

        $this->assertTrue($request->attributes->has('_configurations'));
        $this->assertCount(2, $request->attributes->get('_configurations'));
    }

    public function testOnControllerWithDuplicatedQueryParam()
    {
        $this->expectException(\LogicException::class);
        $event = $this->createControllerEvent([new AnnotatedController(), 'duplicatedQueryParamConfiguration']);
        $this->listener->onController($event);
    }

    private function createControllerEvent(callable $controller): ControllerEvent
    {
        $kernel = new KernelForTest('test', true);
        $event = new ControllerEvent($kernel, $controller, new Request(), HttpKernelInterface::MASTER_REQUEST);

        return $event;
    }
}
