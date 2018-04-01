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
use Symphony\Component\DependencyInjection\Argument\TaggedIteratorArgument;
use Symphony\Component\DependencyInjection\Compiler\ResolveTaggedIteratorArgumentPass;
use Symphony\Component\DependencyInjection\ContainerBuilder;
use Symphony\Component\DependencyInjection\Reference;

/**
 * @author Roland Franssen <franssen.roland@gmail.com>
 */
class ResolveTaggedIteratorArgumentPassTest extends TestCase
{
    public function testProcess()
    {
        $container = new ContainerBuilder();
        $container->register('a', 'stdClass')->addTag('foo');
        $container->register('b', 'stdClass')->addTag('foo', array('priority' => 20));
        $container->register('c', 'stdClass')->addTag('foo', array('priority' => 10));
        $container->register('d', 'stdClass')->setProperty('foos', new TaggedIteratorArgument('foo'));

        (new ResolveTaggedIteratorArgumentPass())->process($container);

        $properties = $container->getDefinition('d')->getProperties();
        $expected = new TaggedIteratorArgument('foo');
        $expected->setValues(array(new Reference('b'), new Reference('c'), new Reference('a')));
        $this->assertEquals($expected, $properties['foos']);
    }
}
