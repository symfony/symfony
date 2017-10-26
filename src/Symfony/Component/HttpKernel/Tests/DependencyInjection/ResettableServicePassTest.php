<?php

namespace Symfony\Component\HttpKernel\Tests\DependencyInjection;

use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\Argument\IteratorArgument;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\DependencyInjection\ResettableServicePass;
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

        $container->register('request_stack', RequestStack::class)
            ->setPublic(true);
        $container->addCompilerPass(new ResettableServicePass('kernel.reset'));

        $container->compile();

        $definition = $container->getDefinition('request_stack');

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
        $container->register('request_stack', RequestStack::class)
            ->setArguments(array(null, array()));
        $container->addCompilerPass(new ResettableServicePass('kernel.reset'));

        $container->compile();
    }
}
