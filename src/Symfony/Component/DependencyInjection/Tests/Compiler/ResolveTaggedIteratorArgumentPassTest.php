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
use Symfony\Component\DependencyInjection\Argument\TaggedIteratorArgument;
use Symfony\Component\DependencyInjection\Compiler\ResolveTaggedIteratorArgumentPass;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\DependencyInjection\TypedReference;

/**
 * @author Roland Franssen <franssen.roland@gmail.com>
 */
class ResolveTaggedIteratorArgumentPassTest extends TestCase
{
    public function testProcess()
    {
        $container = new ContainerBuilder();
        $container->register('a', 'stdClass')->addTag('foo');
        $container->register('b', 'stdClass')->addTag('foo', ['priority' => 20]);
        $container->register('c', 'stdClass')->addTag('foo', ['priority' => 10]);
        $container->register('d', 'stdClass')->setProperty('foos', new TaggedIteratorArgument('foo'));

        (new ResolveTaggedIteratorArgumentPass())->process($container);

        $properties = $container->getDefinition('d')->getProperties();
        $expected = new TaggedIteratorArgument('foo');
        $expected->setValues([new Reference('b'), new Reference('c'), new Reference('a')]);
        $this->assertEquals($expected, $properties['foos']);
    }

    public function testProcessWithIndexes()
    {
        $container = new ContainerBuilder();
        $container->register('service_a', 'stdClass')->addTag('foo', ['key' => '1']);
        $container->register('service_b', 'stdClass')->addTag('foo', ['key' => '2']);
        $container->register('service_c', 'stdClass')->setProperty('foos', new TaggedIteratorArgument('foo', 'key'));

        (new ResolveTaggedIteratorArgumentPass())->process($container);

        $properties = $container->getDefinition('service_c')->getProperties();

        $expected = new TaggedIteratorArgument('foo', 'key');
        $expected->setValues(['1' => new TypedReference('service_a', 'stdClass'), '2' => new TypedReference('service_b', 'stdClass')]);
        $this->assertEquals($expected, $properties['foos']);
    }

    public function testProcesWithAutoExcludeReferencingService()
    {
        $container = new ContainerBuilder();
        $container->register('service_a', 'stdClass')->addTag('foo', ['key' => '1']);
        $container->register('service_b', 'stdClass')->addTag('foo', ['key' => '2']);
        $container->register('service_c', 'stdClass')->addTag('foo', ['key' => '3'])->setProperty('foos', new TaggedIteratorArgument('foo', 'key'));

        (new ResolveTaggedIteratorArgumentPass())->process($container);

        $properties = $container->getDefinition('service_c')->getProperties();

        $expected = new TaggedIteratorArgument('foo', 'key');
        $expected->setValues(['1' => new TypedReference('service_a', 'stdClass'), '2' => new TypedReference('service_b', 'stdClass')]);
        $this->assertEquals($expected, $properties['foos']);
    }

    public function testProcesWithoutAutoExcludeReferencingService()
    {
        $container = new ContainerBuilder();
        $container->register('service_a', 'stdClass')->addTag('foo', ['key' => '1']);
        $container->register('service_b', 'stdClass')->addTag('foo', ['key' => '2']);
        $container->register('service_c', 'stdClass')->addTag('foo', ['key' => '3'])->setProperty('foos', new TaggedIteratorArgument(tag: 'foo', indexAttribute: 'key', excludeSelf: false));

        (new ResolveTaggedIteratorArgumentPass())->process($container);

        $properties = $container->getDefinition('service_c')->getProperties();

        $expected = new TaggedIteratorArgument(tag: 'foo', indexAttribute: 'key', excludeSelf: false);
        $expected->setValues(['1' => new TypedReference('service_a', 'stdClass'), '2' => new TypedReference('service_b', 'stdClass'),  '3' => new TypedReference('service_c', 'stdClass')]);
        $this->assertEquals($expected, $properties['foos']);
    }
}
