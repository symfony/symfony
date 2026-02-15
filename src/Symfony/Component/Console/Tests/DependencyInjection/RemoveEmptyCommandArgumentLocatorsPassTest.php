<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Console\Tests\DependencyInjection;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\DependencyInjection\RemoveEmptyCommandArgumentLocatorsPass;
use Symfony\Component\DependencyInjection\Argument\ServiceClosureArgument;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\DependencyInjection\ServiceLocator;

class RemoveEmptyCommandArgumentLocatorsPassTest extends TestCase
{
    public function testProcessWithoutServiceResolver()
    {
        $container = new ContainerBuilder();
        $pass = new RemoveEmptyCommandArgumentLocatorsPass();

        $pass->process($container);

        $this->assertTrue(true);
    }

    public function testProcessWithNullArgument()
    {
        $container = new ContainerBuilder();
        $container->register('console.argument_resolver.service')->addArgument(null);

        $pass = new RemoveEmptyCommandArgumentLocatorsPass();
        $pass->process($container);

        $this->assertTrue(true);
    }

    public function testProcessRemovesEmptyLocators()
    {
        $container = new ContainerBuilder();

        $emptyArgumentLocator = new Definition(ServiceLocator::class, [[]]);
        $container->setDefinition('empty_argument_locator', $emptyArgumentLocator);

        $nonEmptyArgumentLocator = new Definition(ServiceLocator::class, [['service' => new Reference('some.service')]]);
        $container->setDefinition('non_empty_argument_locator', $nonEmptyArgumentLocator);

        $commandLocator = new Definition(ServiceLocator::class, [[
            'empty:command' => new ServiceClosureArgument(new Reference('empty_argument_locator')),
            'non-empty:command' => new ServiceClosureArgument(new Reference('non_empty_argument_locator')),
        ]]);
        $container->setDefinition('command_locator', $commandLocator);

        $serviceResolver = new Definition('stdClass', [new Reference('command_locator')]);
        $container->setDefinition('console.argument_resolver.service', $serviceResolver);

        $pass = new RemoveEmptyCommandArgumentLocatorsPass();
        $pass->process($container);

        $commandLocatorDef = $container->getDefinition('command_locator');
        $commands = $commandLocatorDef->getArgument(0);

        $this->assertArrayNotHasKey('empty:command', $commands);
        $this->assertArrayHasKey('non-empty:command', $commands);
    }

    public function testProcessWithFactory()
    {
        $container = new ContainerBuilder();

        $emptyArgumentLocator = new Definition(ServiceLocator::class, [[]]);
        $container->setDefinition('empty_argument_locator_inner', $emptyArgumentLocator);

        $emptyArgumentLocatorWrapper = new Definition(ServiceLocator::class);
        $emptyArgumentLocatorWrapper->setFactory([new Reference('empty_argument_locator_inner'), 'getInstance']);
        $container->setDefinition('empty_argument_locator', $emptyArgumentLocatorWrapper);

        $commandLocator = new Definition(ServiceLocator::class, [[
            'empty:command' => new ServiceClosureArgument(new Reference('empty_argument_locator')),
        ]]);
        $container->setDefinition('command_locator_inner', $commandLocator);

        $commandLocatorWrapper = new Definition(ServiceLocator::class);
        $commandLocatorWrapper->setFactory([new Reference('command_locator_inner'), 'getInstance']);
        $container->setDefinition('command_locator', $commandLocatorWrapper);

        $serviceResolver = new Definition('stdClass', [new Reference('command_locator')]);
        $container->setDefinition('console.argument_resolver.service', $serviceResolver);

        $pass = new RemoveEmptyCommandArgumentLocatorsPass();
        $pass->process($container);

        $commandLocatorDef = $container->getDefinition('command_locator_inner');
        $commands = $commandLocatorDef->getArgument(0);

        $this->assertArrayNotHasKey('empty:command', $commands);
    }

    public function testProcessPreservesNonEmptyLocators()
    {
        $container = new ContainerBuilder();

        $argumentLocator = new Definition(ServiceLocator::class, [['logger' => new Reference('logger')]]);
        $container->setDefinition('argument_locator', $argumentLocator);

        $commandLocator = new Definition(ServiceLocator::class, [[
            'test:command' => new ServiceClosureArgument(new Reference('argument_locator')),
        ]]);
        $container->setDefinition('command_locator', $commandLocator);

        $serviceResolver = new Definition('stdClass', [new Reference('command_locator')]);
        $container->setDefinition('console.argument_resolver.service', $serviceResolver);

        $pass = new RemoveEmptyCommandArgumentLocatorsPass();
        $pass->process($container);

        $commandLocatorDef = $container->getDefinition('command_locator');
        $commands = $commandLocatorDef->getArgument(0);

        $this->assertArrayHasKey('test:command', $commands);
    }
}
