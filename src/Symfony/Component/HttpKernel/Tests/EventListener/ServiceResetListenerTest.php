<?php

namespace Symfony\Component\HttpKernel\Tests\EventListener;

use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\Argument\IteratorArgument;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\HttpKernel\EventListener\ServiceResetListener;
use Symfony\Component\HttpKernel\Tests\Fixtures\ClearableService;
use Symfony\Component\HttpKernel\Tests\Fixtures\ResettableService;

class ServiceResetListenerTest extends TestCase
{
    protected function setUp()
    {
        ResettableService::$counter = 0;
        ClearableService::$counter = 0;
    }

    public function testResetServicesNoOp()
    {
        $container = $this->buildContainer();
        $container->get('reset_subscriber')->onKernelTerminate();

        $this->assertEquals(0, ResettableService::$counter);
        $this->assertEquals(0, ClearableService::$counter);
    }

    public function testResetServicesPartially()
    {
        $container = $this->buildContainer();
        $container->get('one');
        $container->get('reset_subscriber')->onKernelTerminate();

        $this->assertEquals(1, ResettableService::$counter);
        $this->assertEquals(0, ClearableService::$counter);
    }

    public function testResetServicesTwice()
    {
        $container = $this->buildContainer();
        $container->get('one');
        $container->get('reset_subscriber')->onKernelTerminate();
        $container->get('two');
        $container->get('reset_subscriber')->onKernelTerminate();

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

        $container->register('reset_subscriber', ServiceResetListener::class)
            ->setPublic(true)
            ->addArgument(new IteratorArgument(array(
                'one' => new Reference('one', ContainerInterface::IGNORE_ON_UNINITIALIZED_REFERENCE),
                'two' => new Reference('two', ContainerInterface::IGNORE_ON_UNINITIALIZED_REFERENCE),
            )))
            ->addArgument(array(
                'one' => 'reset',
                'two' => 'clear',
            ));

        $container->compile();

        return $container;
    }
}
