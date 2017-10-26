<?php

namespace Symfony\Component\HttpKernel\Tests\DependencyInjection;

use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\Argument\IteratorArgument;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\HttpKernel\DependencyInjection\ResettableServicePass;
use Symfony\Component\HttpKernel\HttpKernel;
use Symfony\Component\HttpKernel\Middleware\ServiceResetMiddleware;
use Symfony\Component\HttpKernel\Tests\Fixtures\ClearableService;
use Symfony\Component\HttpKernel\Tests\Fixtures\ResettableService;

class ResettableServicePassTest extends TestCase
{
    public function testCompilerPass()
    {
        $container = new ContainerBuilder();
        $container->register(HttpKernel::class)->setSynthetic(true);
        $container->register('one', ResettableService::class)
            ->setPublic(true)
            ->addTag('kernel.reset', array('method' => 'reset'));
        $container->register('two', ClearableService::class)
            ->setPublic(true)
            ->addTag('kernel.reset', array('method' => 'clear'));

        $container->register(ServiceResetMiddleware::class)
            ->setPublic(true)
            ->setArguments(array(
                '$httpKernel' => new Reference(HttpKernel::class),
                '$services' => null,
                '$resetMethods' => array()
            ));
        $container->addCompilerPass(new ResettableServicePass('kernel.reset'));

        $container->compile();

        $definition = $container->getDefinition(ServiceResetMiddleware::class);

        $this->assertEquals(
            array(
                new Reference(HttpKernel::class),
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
        $container->register(HttpKernel::class)->setSynthetic(true);
        $container->register(ResettableService::class)
            ->addTag('kernel.reset');
        $container->register(ServiceResetMiddleware::class)
            ->setArguments(array(
                '$httpKernel' => new Reference(HttpKernel::class),
                '$services' => null,
                '$resetMethods' => array()
            ));
        $container->addCompilerPass(new ResettableServicePass('kernel.reset'));

        $container->compile();
    }

    public function testCompilerPassWithoutListener()
    {
        $container = new ContainerBuilder();
        $container->addCompilerPass(new ResettableServicePass());

        $container->compile();

        $this->assertFalse($container->has(ServiceResetMiddleware::class));
    }
}
