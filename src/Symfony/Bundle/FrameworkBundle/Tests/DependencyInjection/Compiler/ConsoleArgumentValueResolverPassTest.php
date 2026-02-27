<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\FrameworkBundle\Tests\DependencyInjection\Compiler;

use PHPUnit\Framework\TestCase;
use Symfony\Bundle\FrameworkBundle\DependencyInjection\Compiler\ConsoleArgumentValueResolverPass;
use Symfony\Component\Console\ArgumentResolver\ArgumentResolver;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\Stopwatch\Stopwatch;

class ConsoleArgumentValueResolverPassTest extends TestCase
{
    public function testServicesAreOrderedAccordingToPriority()
    {
        $services = [
            'n3' => [[]],
            'n1' => [['priority' => 200]],
            'n2' => [['priority' => 100]],
        ];

        $expected = [
            new Reference('n1'),
            new Reference('n2'),
            new Reference('n3'),
        ];

        $definition = new Definition(ArgumentResolver::class, [[], null]);
        $container = new ContainerBuilder();
        $container->setDefinition('console.argument_resolver', $definition);

        foreach ($services as $id => [$tag]) {
            $container->register($id)->addTag('console.argument_value_resolver', $tag);
        }

        $container->setParameter('kernel.debug', false);

        (new ConsoleArgumentValueResolverPass())->process($container);
        $this->assertEquals($expected, $definition->getArgument(0)->getValues());

        $this->assertFalse($container->hasDefinition('.debug.console.value_resolver.n1'));
        $this->assertFalse($container->hasDefinition('.debug.console.value_resolver.n2'));
        $this->assertFalse($container->hasDefinition('.debug.console.value_resolver.n3'));
    }

    public function testInDebugWithStopWatchDefinition()
    {
        $services = [
            'n3' => [[]],
            'n1' => [['priority' => 200]],
            'n2' => [['priority' => 100]],
        ];

        $expected = [
            new Reference('.debug.console.value_resolver.n1'),
            new Reference('.debug.console.value_resolver.n2'),
            new Reference('.debug.console.value_resolver.n3'),
        ];

        $definition = new Definition(ArgumentResolver::class, [[], null]);
        $container = new ContainerBuilder();
        $container->register('debug.stopwatch', Stopwatch::class);
        $container->setDefinition('console.argument_resolver', $definition);

        foreach ($services as $id => [$tag]) {
            $container->register($id)->addTag('console.argument_value_resolver', $tag);
        }

        $container->setParameter('kernel.debug', true);

        (new ConsoleArgumentValueResolverPass())->process($container);
        $this->assertEquals($expected, $definition->getArgument(0)->getValues());

        $this->assertTrue($container->hasDefinition('.debug.console.value_resolver.n1'));
        $this->assertTrue($container->hasDefinition('.debug.console.value_resolver.n2'));
        $this->assertTrue($container->hasDefinition('.debug.console.value_resolver.n3'));

        $this->assertTrue($container->hasDefinition('n1'));
        $this->assertTrue($container->hasDefinition('n2'));
        $this->assertTrue($container->hasDefinition('n3'));
    }
}
