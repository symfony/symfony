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

class AnnotatedControllerListenerTest extends TestCase
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
        $request = new Request();

        $kernel = new KernelForTest('test', true);
        $event = new ControllerEvent(
            $kernel,
            [new AnnotatedController(), 'queryParamAction'],
            $request,
            HttpKernelInterface::MASTER_REQUEST
        );

        $this->listener->onController($event);

        $this->assertTrue($request->attributes->has('_configurations'));
        $this->assertCount(2, $request->attributes->get('_configurations'));
    }

    private function createControllerEvent(callable $controller): ControllerEvent
    {
        $kernel = new KernelForTest('test', true);
        $event = new ControllerEvent($kernel, $controller, new Request(), HttpKernelInterface::MASTER_REQUEST);

        return $event;
    }
}
