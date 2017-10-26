<?php

namespace Symfony\Component\HttpKernel\Tests\Middleware;

use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\Argument\IteratorArgument;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\HttpKernel;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\HttpKernel\Middleware\ServiceResetMiddleware;
use Symfony\Component\HttpKernel\Tests\Fixtures\ClearableService;
use Symfony\Component\HttpKernel\Tests\Fixtures\ResettableService;

class ServiceResetMiddlewareTest extends TestCase
{
    protected function setUp()
    {
        ResettableService::$counter = 0;
        ClearableService::$counter = 0;
    }

    public function testResetServicesNoOp()
    {
        $container = $this->buildContainer();
        $container->get('reset_middleware')->handle(new Request());

        $this->assertEquals(0, ResettableService::$counter);
        $this->assertEquals(0, ClearableService::$counter);
    }

    public function testNoResetOnSubRequests()
    {
        $container = $this->buildContainer();
        $container->get('one');
        $container->get('reset_middleware')->handle(new Request(), HttpKernelInterface::SUB_REQUEST);

        $this->assertEquals(0, ResettableService::$counter);
        $this->assertEquals(0, ClearableService::$counter);
    }

    public function testResetServicesPartially()
    {
        $container = $this->buildContainer();
        $container->get('one');
        $container->get('reset_middleware')->handle(new Request());

        $this->assertEquals(1, ResettableService::$counter);
        $this->assertEquals(0, ClearableService::$counter);
    }

    public function testResetServicesTwice()
    {
        $container = $this->buildContainer();
        $container->get('one');
        $container->get('reset_middleware')->handle(new Request());
        $container->get('two');
        $container->get('reset_middleware')->handle(new Request());

        $this->assertEquals(2, ResettableService::$counter);
        $this->assertEquals(1, ClearableService::$counter);
    }

    /**
     * @return ContainerBuilder
     */
    private function buildContainer()
    {
        $container = new ContainerBuilder();
        $container->register('one', ResettableService::class)->setPublic(true);
        $container->register('two', ClearableService::class)->setPublic(true);
        $container->register(HttpKernel::class, HttpKernelInterface::class)
            ->setSynthetic(true);

        $container->register('reset_middleware', ServiceResetMiddleware::class)
            ->setPublic(true)
            ->addArgument(new Reference(HttpKernel::class))
            ->addArgument(new IteratorArgument(array(
                'one' => new Reference('one', ContainerInterface::IGNORE_ON_UNINITIALIZED_REFERENCE),
                'two' => new Reference('two', ContainerInterface::IGNORE_ON_UNINITIALIZED_REFERENCE),
            )))
            ->addArgument(array(
                'one' => 'reset',
                'two' => 'clear',
            ));

        $container->compile();

        $kernelMock = $this->createMock(HttpKernelInterface::class);
        $kernelMock->expects($this->any())
            ->method('handle')
            ->willReturn(new Response());

        $container->set(HttpKernel::class, $kernelMock);

        return $container;
    }
}
