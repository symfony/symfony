<?php

/*
 * This file is part of the Symphony package.
 *
 * (c) Fabien Potencier <fabien@symphony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symphony\Component\DependencyInjection\Tests\Compiler;

use PHPUnit\Framework\TestCase;
use Symphony\Component\DependencyInjection\Argument\BoundArgument;
use Symphony\Component\DependencyInjection\Definition;
use Symphony\Component\DependencyInjection\Compiler\CheckExceptionOnInvalidReferenceBehaviorPass;
use Symphony\Component\DependencyInjection\Reference;
use Symphony\Component\DependencyInjection\ContainerBuilder;

class CheckExceptionOnInvalidReferenceBehaviorPassTest extends TestCase
{
    public function testProcess()
    {
        $container = new ContainerBuilder();

        $container
            ->register('a', '\stdClass')
            ->addArgument(new Reference('b'))
        ;
        $container->register('b', '\stdClass');

        $this->process($container);

        $this->addToAssertionCount(1);
    }

    /**
     * @expectedException \Symphony\Component\DependencyInjection\Exception\ServiceNotFoundException
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

    /**
     * @expectedException \Symphony\Component\DependencyInjection\Exception\ServiceNotFoundException
     */
    public function testProcessThrowsExceptionOnInvalidReferenceFromInlinedDefinition()
    {
        $container = new ContainerBuilder();

        $def = new Definition();
        $def->addArgument(new Reference('b'));

        $container
            ->register('a', '\stdClass')
            ->addArgument($def)
        ;

        $this->process($container);
    }

    /**
     * @expectedException \Symphony\Component\DependencyInjection\Exception\InvalidArgumentException
     * @expectedExceptionMessage Invalid ignore-on-uninitialized reference found in service
     */
    public function testProcessThrowsExceptionOnNonSharedUninitializedReference()
    {
        $container = new ContainerBuilder();

        $container
            ->register('a', 'stdClass')
            ->addArgument(new Reference('b', $container::IGNORE_ON_UNINITIALIZED_REFERENCE))
        ;

        $container
            ->register('b', 'stdClass')
            ->setShared(false)
        ;

        $this->process($container);
    }

    public function testProcessDefinitionWithBindings()
    {
        $container = new ContainerBuilder();

        $container
            ->register('b')
            ->setBindings(array(new BoundArgument(new Reference('a'))))
        ;

        $this->process($container);

        $this->addToAssertionCount(1);
    }

    private function process(ContainerBuilder $container)
    {
        $pass = new CheckExceptionOnInvalidReferenceBehaviorPass();
        $pass->process($container);
    }
}
