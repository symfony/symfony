<?php

namespace Symphony\Component\HttpKernel\Tests\DependencyInjection;

use PHPUnit\Framework\TestCase;
use Symphony\Component\DependencyInjection\Argument\IteratorArgument;
use Symphony\Component\DependencyInjection\ContainerBuilder;
use Symphony\Component\DependencyInjection\ContainerInterface;
use Symphony\Component\DependencyInjection\Reference;
use Symphony\Component\HttpKernel\DependencyInjection\ResettableServicePass;
use Symphony\Component\HttpKernel\DependencyInjection\ServicesResetter;
use Symphony\Component\HttpKernel\Tests\Fixtures\ClearableService;
use Symphony\Component\HttpKernel\Tests\Fixtures\ResettableService;

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

        $container->register('services_resetter', ServicesResetter::class)
            ->setPublic(true)
            ->setArguments(array(null, array()));
        $container->addCompilerPass(new ResettableServicePass());

        $container->compile();

        $definition = $container->getDefinition('services_resetter');

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
     * @expectedException \Symphony\Component\DependencyInjection\Exception\RuntimeException
     * @expectedExceptionMessage Tag kernel.reset requires the "method" attribute to be set.
     */
    public function testMissingMethod()
    {
        $container = new ContainerBuilder();
        $container->register(ResettableService::class)
            ->addTag('kernel.reset');
        $container->register('services_resetter', ServicesResetter::class)
            ->setArguments(array(null, array()));
        $container->addCompilerPass(new ResettableServicePass());

        $container->compile();
    }

    public function testCompilerPassWithoutResetters()
    {
        $container = new ContainerBuilder();
        $container->register('services_resetter', ServicesResetter::class)
            ->setArguments(array(null, array()));
        $container->addCompilerPass(new ResettableServicePass());

        $container->compile();

        $this->assertFalse($container->has('services_resetter'));
    }
}
