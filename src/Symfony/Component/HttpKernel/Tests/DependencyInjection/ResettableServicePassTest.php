<?php

namespace Symfony\Component\HttpKernel\Tests\DependencyInjection;

use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\Argument\IteratorArgument;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\HttpKernel\DependencyInjection\ResettableServicePass;
use Symfony\Component\HttpKernel\EventListener\ServiceResetListener;
use Symfony\Component\HttpKernel\Tests\Fixtures\ClearableService;
use Symfony\Component\HttpKernel\Tests\Fixtures\ResettableService;

class ResettableServicePassTest extends TestCase
{
    public function testCompilerPass()
    {
        $container = new ContainerBuilder();
        $container->register('one', ResettableService::class)
            ->setPublic(true)
            ->addTag('kernel.reset', array('method' => 'reset'));
        $container->register('two', ClearableService::class)
            ->setPublic(true)
            ->addTag('kernel.reset', array('method' => 'clear'));

        $container->register(ServiceResetListener::class)
            ->setPublic(true)
            ->setArguments(array(null, array()));
        $container->addCompilerPass(new ResettableServicePass('kernel.reset'));

        $container->compile();

        $definition = $container->getDefinition(ServiceResetListener::class);

        $this->assertEquals(
            array(
                new IteratorArgument(array(
                    'one' => new Reference('one', ContainerInterface::IGNORE_ON_UNINITIALIZED_REFERENCE),
                    'two' => new Reference('two', ContainerInterface::IGNORE_ON_UNINITIALIZED_REFERENCE),
                )),
                array(
                    'one' => 'reset',
                    'two' => 'clear',
                ),
            ),
            $definition->getArguments()
        );
    }

    /**
     * @expectedException \Symfony\Component\DependencyInjection\Exception\RuntimeException
     * @expectedExceptionMessage Tag kernel.reset requires the "method" attribute to be set.
     */
    public function testMissingMethod()
    {
        $container = new ContainerBuilder();
        $container->register(ResettableService::class)
            ->addTag('kernel.reset');
        $container->register(ServiceResetListener::class)
            ->setArguments(array(null, array()));
        $container->addCompilerPass(new ResettableServicePass('kernel.reset'));

        $container->compile();
    }

    public function testCompilerPassWithoutResetters()
    {
        $container = new ContainerBuilder();
        $container->register(ServiceResetListener::class)
            ->setArguments(array(null, array()));
        $container->addCompilerPass(new ResettableServicePass());

        $container->compile();

        $this->assertFalse($container->has(ServiceResetListener::class));
    }

    public function testCompilerPassWithoutListener()
    {
        $container = new ContainerBuilder();
        $container->addCompilerPass(new ResettableServicePass());

        $container->compile();

        $this->assertFalse($container->has(ServiceResetListener::class));
    }
}
