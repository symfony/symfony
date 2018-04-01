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
use Symphony\Component\DependencyInjection\Compiler\DefinitionErrorExceptionPass;
use Symphony\Component\DependencyInjection\ContainerBuilder;
use Symphony\Component\DependencyInjection\Definition;

class DefinitionErrorExceptionPassTest extends TestCase
{
    /**
     * @expectedException \Symphony\Component\DependencyInjection\Exception\RuntimeException
     * @expectedExceptionMessage Things went wrong!
     */
    public function testThrowsException()
    {
        $container = new ContainerBuilder();
        $def = new Definition();
        $def->addError('Things went wrong!');
        $def->addError('Now something else!');
        $container->register('foo_service_id')
            ->setArguments(array(
                $def,
            ));

        $pass = new DefinitionErrorExceptionPass();
        $pass->process($container);
    }

    public function testNoExceptionThrown()
    {
        $container = new ContainerBuilder();
        $def = new Definition();
        $container->register('foo_service_id')
            ->setArguments(array(
                $def,
            ));

        $pass = new DefinitionErrorExceptionPass();
        $pass->process($container);
        $this->assertSame($def, $container->getDefinition('foo_service_id')->getArgument(0));
    }
}
