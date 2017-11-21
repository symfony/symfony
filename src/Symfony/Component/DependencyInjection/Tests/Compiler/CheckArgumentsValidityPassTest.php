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

use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\Compiler\CheckArgumentsValidityPass;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * @author Kévin Dunglas <dunglas@gmail.com>
 */
class CheckArgumentsValidityPassTest extends TestCase
{
    public function testProcess()
    {
        $container = new ContainerBuilder();
        $definition = $container->register('foo');
        $definition->setArguments(array(null, 1, 'a'));
        $definition->setMethodCalls(array(
            array('bar', array('a', 'b')),
            array('baz', array('c', 'd')),
        ));

        $pass = new CheckArgumentsValidityPass();
        $pass->process($container);

        $this->assertEquals(array(null, 1, 'a'), $container->getDefinition('foo')->getArguments());
        $this->assertEquals(array(
            array('bar', array('a', 'b')),
            array('baz', array('c', 'd')),
        ), $container->getDefinition('foo')->getMethodCalls());
    }

    /**
     * @expectedException \Symfony\Component\DependencyInjection\Exception\RuntimeException
     * @dataProvider definitionProvider
     */
    public function testException(array $arguments, array $methodCalls)
    {
        $container = new ContainerBuilder();
        $definition = $container->register('foo');
        $definition->setArguments($arguments);
        $definition->setMethodCalls($methodCalls);

        $pass = new CheckArgumentsValidityPass();
        $pass->process($container);
    }

    public function definitionProvider()
    {
        return array(
            array(array(null, 'a' => 'a'), array()),
            array(array(1 => 1), array()),
            array(array(), array(array('baz', array(null, 'a' => 'a')))),
            array(array(), array(array('baz', array(1 => 1)))),
        );
    }

    public function testNoException()
    {
        $container = new ContainerBuilder();
        $definition = $container->register('foo');
        $definition->setArguments(array(null, 'a' => 'a'));

        $pass = new CheckArgumentsValidityPass(false);
        $pass->process($container);
        $this->assertCount(1, $definition->getErrors());
    }
}
