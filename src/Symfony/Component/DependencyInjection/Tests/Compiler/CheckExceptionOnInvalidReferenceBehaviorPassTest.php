<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\DependencyInjection\Tests\Compiler;

use Symfony\Component\DependencyInjection\Definition;

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
     * @expectedException \Symfony\Component\DependencyInjection\Exception\ServiceNotFoundException
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
     * @expectedException \Symfony\Component\DependencyInjection\Exception\ServiceNotFoundException
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

    private function process(ContainerBuilder $container)
    {
        $pass = new CheckExceptionOnInvalidReferenceBehaviorPass();
        $pass->process($container);
    }
}
