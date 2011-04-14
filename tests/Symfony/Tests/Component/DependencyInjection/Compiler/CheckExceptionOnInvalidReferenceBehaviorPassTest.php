<?php

namespace Symfony\Tests\Component\DependencyInjection\Compiler;

use Symfony\Component\DependencyInjection\Compiler\CheckExceptionOnInvalidReferenceBehaviorPass;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\DependencyInjection\ContainerBuilder;

class CheckExceptionOnInvalidReferenceBehaviorPassTest extends \PHPUnit_Framework_TestCase
{
    public function testProcess()
    {
        $container = new ContainerBuilder();

        $container
            ->register('a', '\stdClass')
            ->addArgument(new Reference('b'))
        ;
        $container->register('b', '\stdClass');
    }

    /**
     * @expectedException Symfony\Component\DependencyInjection\Exception\NonExistentServiceException
     */
    public function testProcessThrowsExceptionOnInvalidReference()
    {
        $container = new ContainerBuilder();

        $container
            ->register('a', '\stdClass')
            ->addArgument(new Reference('b'))
        ;

        $this->process($container);
    }

    private function process(ContainerBuilder $container)
    {
        $pass = new CheckExceptionOnInvalidReferenceBehaviorPass();
        $pass->process($container);
    }
}