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

use Symfony\Component\DependencyInjection\Compiler\CollectSimpleTaggedServicesPass;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;

class CollectSimpleTaggedServicesPassTest extends \PHPUnit_Framework_TestCase
{
    public function testProcess()
    {
        $container = new ContainerBuilder();
        $container
            ->register('foo')
        ;
        $container
            ->register('bar1')
            ->addTag('bar', array())
        ;
        $container
            ->register('bar2')
            ->addTag('bar', array())
        ;

        $pass = new CollectSimpleTaggedServicesPass('bar', 'foo', 'setBar');
        $pass->process($container);

        $this->assertEquals(array(
                array('setBar', array(new Reference('bar1'))),
                array('setBar', array(new Reference('bar2'))),
            ), $container->findDefinition('foo')->getMethodCalls());
    }

    public function testProcessWithArgumentWrangling()
    {
        $container = new ContainerBuilder();
        $container
            ->register('foo')
        ;
        $container
            ->register('bar1')
            ->addTag('bar', array('foo' => 'bar'))
        ;

        $pass = new CollectSimpleTaggedServicesPass('bar', 'foo', 'setBar', function ($id, $arguments) {
            return array(new Reference($id), $arguments['foo']);
        });
        $pass->process($container);

        $this->assertEquals(array(
                array('setBar', array(new Reference('bar1'), 'bar')),
            ), $container->findDefinition('foo')->getMethodCalls());
    }
}
