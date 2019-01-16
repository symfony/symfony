<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HttpKernel\Tests\DependencyInjection;

use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\HttpKernel\Controller\ArgumentResolver;
use Symfony\Component\HttpKernel\DependencyInjection\ControllerArgumentValueResolverPass;

class ControllerArgumentValueResolverPassTest extends TestCase
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

        $definition = new Definition(ArgumentResolver::class, [null, []]);
        $container = new ContainerBuilder();
        $container->setDefinition('argument_resolver', $definition);

        foreach ($services as $id => list($tag)) {
            $container->register($id)->addTag('controller.argument_value_resolver', $tag);
        }

        (new ControllerArgumentValueResolverPass())->process($container);
        $this->assertEquals($expected, $definition->getArgument(1)->getValues());
    }

    public function testReturningEmptyArrayWhenNoService()
    {
        $definition = new Definition(ArgumentResolver::class, [null, []]);
        $container = new ContainerBuilder();
        $container->setDefinition('argument_resolver', $definition);

        (new ControllerArgumentValueResolverPass())->process($container);
        $this->assertEquals([], $definition->getArgument(1)->getValues());
    }

    public function testNoArgumentResolver()
    {
        $container = new ContainerBuilder();

        (new ControllerArgumentValueResolverPass())->process($container);

        $this->assertFalse($container->hasDefinition('argument_resolver'));
    }
}
