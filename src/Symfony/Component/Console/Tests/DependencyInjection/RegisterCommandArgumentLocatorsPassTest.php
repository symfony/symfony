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
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\DependencyInjection\RegisterCommandArgumentLocatorsPass;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\DependencyInjection\ServiceLocator;

class RegisterCommandArgumentLocatorsPassTest extends TestCase
{
    public function testProcessWithoutServiceResolver()
    {
        $container = new ContainerBuilder();
        $pass = new RegisterCommandArgumentLocatorsPass();

        $pass->process($container);

        $this->assertTrue(true);
    }

    public function testProcessWithServiceArguments()
    {
        $container = new ContainerBuilder();
        $container->register('logger', LoggerInterface::class);
        $container->register('console.argument_resolver.service')->addArgument(null);

        $command = new Definition(CommandWithServiceArguments::class);
        $command->setAutowired(true);
        $command->addTag('console.command', ['command' => 'test:command']);
        $command->addTag('console.command.service_arguments');
        $container->setDefinition('test.command', $command);

        $pass = new RegisterCommandArgumentLocatorsPass();
        $pass->process($container);

        $serviceResolverDef = $container->getDefinition('console.argument_resolver.service');
        $commandLocatorRef = $serviceResolverDef->getArgument(0);

        $this->assertInstanceOf(Reference::class, $commandLocatorRef);

        $commandLocator = $container->getDefinition((string) $commandLocatorRef);
        $this->assertSame(ServiceLocator::class, $commandLocator->getClass());

        $commands = $commandLocator->getArgument(0);
        $this->assertArrayHasKey('test:command', $commands);
    }

    public function testProcessWithManualArgumentMapping()
    {
        $container = new ContainerBuilder();
        $container->register('my.logger', LoggerInterface::class);
        $container->register('console.argument_resolver.service')->addArgument(null);

        $command = new Definition(CommandWithServiceArguments::class);
        $command->addTag('console.command', ['command' => 'test:command']);
        $command->addTag('console.command.service_arguments', [
            'argument' => 'logger',
            'id' => 'my.logger',
        ]);
        $container->setDefinition('test.command', $command);

        $pass = new RegisterCommandArgumentLocatorsPass();
        $pass->process($container);

        $serviceResolverDef = $container->getDefinition('console.argument_resolver.service');
        $commandLocatorRef = $serviceResolverDef->getArgument(0);

        $commandLocator = $container->getDefinition((string) $commandLocatorRef);
        $commands = $commandLocator->getArgument(0);

        $this->assertArrayHasKey('test:command', $commands);
    }

    public function testProcessSkipsInputOutputParameters()
    {
        $container = new ContainerBuilder();
        $container->register('console.argument_resolver.service')->addArgument(null);

        $command = new Definition(CommandWithInputOutput::class);
        $command->setAutowired(true);
        $command->addTag('console.command', ['command' => 'test:command']);
        $command->addTag('console.command.service_arguments');
        $container->setDefinition('test.command', $command);

        $pass = new RegisterCommandArgumentLocatorsPass();
        $pass->process($container);

        $serviceResolverDef = $container->getDefinition('console.argument_resolver.service');
        $commandLocatorRef = $serviceResolverDef->getArgument(0);

        $commandLocator = $container->getDefinition((string) $commandLocatorRef);
        $commands = $commandLocator->getArgument(0);

        $this->assertArrayNotHasKey('test:command', $commands);
    }

    public function testProcessWithMultipleMethods()
    {
        $container = new ContainerBuilder();
        $container->register('logger', LoggerInterface::class);
        $container->register('console.argument_resolver.service')->addArgument(null);

        $command = new Definition(MultiMethodCommand::class);
        $command->setAutowired(true);
        $command->addTag('console.command', ['command' => 'test:cmd1', 'method' => 'cmd1']);
        $command->addTag('console.command', ['command' => 'test:cmd2', 'method' => 'cmd2']);
        $command->addTag('console.command.service_arguments');
        $container->setDefinition('test.command', $command);

        $pass = new RegisterCommandArgumentLocatorsPass();
        $pass->process($container);

        $serviceResolverDef = $container->getDefinition('console.argument_resolver.service');
        $commandLocatorRef = $serviceResolverDef->getArgument(0);

        $commandLocator = $container->getDefinition((string) $commandLocatorRef);
        $commands = $commandLocator->getArgument(0);

        $this->assertArrayHasKey('test:cmd1', $commands);
        $this->assertArrayHasKey('test:cmd2', $commands);
    }

    public function testProcessThrowsOnMissingArgumentAttribute()
    {
        $container = new ContainerBuilder();
        $container->register('console.argument_resolver.service')->addArgument(null);

        $command = new Definition(CommandWithServiceArguments::class);
        $command->addTag('console.command', ['command' => 'test:command']);
        $command->addTag('console.command.service_arguments', ['argument' => 'logger']);
        $container->setDefinition('test.command', $command);

        $pass = new RegisterCommandArgumentLocatorsPass();

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Missing "id" attribute');

        $pass->process($container);
    }

    public function testProcessThrowsOnMissingIdAttribute()
    {
        $container = new ContainerBuilder();
        $container->register('console.argument_resolver.service')->addArgument(null);

        $command = new Definition(CommandWithServiceArguments::class);
        $command->addTag('console.command', ['command' => 'test:command']);
        $command->addTag('console.command.service_arguments', ['id' => 'my.logger']);
        $container->setDefinition('test.command', $command);

        $pass = new RegisterCommandArgumentLocatorsPass();

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Missing "argument" attribute');

        $pass->process($container);
    }
}

class CommandWithServiceArguments
{
    public function __invoke(LoggerInterface $logger): void
    {
    }
}

class CommandWithInputOutput
{
    public function __invoke(\Symfony\Component\Console\Input\InputInterface $input, \Symfony\Component\Console\Output\OutputInterface $output): void
    {
    }
}

class MultiMethodCommand
{
    public function cmd1(LoggerInterface $logger): void
    {
    }

    public function cmd2(LoggerInterface $logger): void
    {
    }
}
