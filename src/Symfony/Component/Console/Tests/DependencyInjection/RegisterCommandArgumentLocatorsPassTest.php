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

use PHPUnit\Framework\Attributes\DoesNotPerformAssertions;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Symfony\Component\Console\DependencyInjection\RegisterCommandArgumentLocatorsPass;
use Symfony\Component\DependencyInjection\Argument\ServiceClosureArgument;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\DependencyInjection\Attribute\Target;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Exception\ServiceNotFoundException;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\DependencyInjection\ServiceLocator;
use Symfony\Component\DependencyInjection\TypedReference;

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

    #[DoesNotPerformAssertions]
    public function testProcessServiceArgumentWithAutowireAttribute()
    {
        $container = new ContainerBuilder();
        $container->register('console.argument_resolver.service')->setSynthetic(true)->addArgument(null);
        $container->register('logger')->setSynthetic(true);

        $command = new Definition(CommandWithAutowireAttribute::class);
        $command->addTag('console.command', ['command' => 'test:command']);
        $command->addTag('console.command.service_arguments');
        $container->setDefinition('test.command', $command);

        $pass = new RegisterCommandArgumentLocatorsPass();

        $pass->process($container);
        $container->compile();
    }

    #[DoesNotPerformAssertions]
    public function testProcessNullableAutowireAttributeWithInvalidService()
    {
        $container = new ContainerBuilder();
        $container->register('console.argument_resolver.service')->setSynthetic(true)->addArgument(null);

        $command = new Definition(CommandWithAutowireAttributeNullableInvalidReference::class);
        $command->setAutowired(true);
        $command->addTag('console.command', ['command' => 'test:command']);
        $command->addTag('console.command.service_arguments');
        $container->setDefinition('test.command', $command);

        $pass = new RegisterCommandArgumentLocatorsPass();
        $pass->process($container);

        $container->compile();
    }

    public function testProcessThrowsOnInvalidAutowiredService()
    {
        $container = new ContainerBuilder();
        $container->register('console.argument_resolver.service')->setSynthetic(true)->addArgument(null);

        $command = new Definition(CommandWithAutowireAttributeInvalidReference::class);
        $command->setAutowired(true);
        $command->addTag('console.command', ['command' => 'test:command']);
        $command->addTag('console.command.service_arguments');
        $container->setDefinition('test.command', $command);

        $pass = new RegisterCommandArgumentLocatorsPass();
        $pass->process($container);

        $this->expectException(ServiceNotFoundException::class);
        $container->compile();
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

    public function testProcessForwardsTargetAttribute()
    {
        $container = new ContainerBuilder();
        $container->register('console.argument_resolver.service')->addArgument(null);

        $command = new Definition(CommandWithTarget::class);
        $command->setAutowired(true);
        $command->addTag('console.command', ['command' => 'test:command']);
        $command->addTag('console.command.service_arguments');
        $container->setDefinition('test.command', $command);

        $pass = new RegisterCommandArgumentLocatorsPass();
        $pass->process($container);

        $serviceResolverDef = $container->getDefinition('console.argument_resolver.service');
        $commands = $container->getDefinition((string) $serviceResolverDef->getArgument(0))->getArgument(0);
        $locator = $container->getDefinition((string) $commands['test:command']->getValues()[0]);
        $locator = $container->getDefinition((string) $locator->getFactory()[0]);

        $expected = ['logger' => new ServiceClosureArgument(new TypedReference(LoggerInterface::class, LoggerInterface::class, ContainerInterface::RUNTIME_EXCEPTION_ON_INVALID_REFERENCE, 'requestLogger', [new Target('request.logger')]))];
        $this->assertEquals($expected, $locator->getArgument(0));
    }

    public function testProcessTargetedArgumentIsAutowired()
    {
        $container = new ContainerBuilder();
        $resolver = $container->register('console.argument_resolver.service', 'stdClass')->addArgument(null);

        $container->register('request.logger', NullLogger::class);
        $container->registerAliasForArgument('request.logger', LoggerInterface::class);

        $command = new Definition(CommandWithTarget::class);
        $command->setAutowired(true);
        $command->addTag('console.command', ['command' => 'test:command']);
        $command->addTag('console.command.service_arguments');
        $container->setDefinition('test.command', $command);

        $pass = new RegisterCommandArgumentLocatorsPass();
        $pass->process($container);

        $locatorId = (string) $resolver->getArgument(0);
        $container->getDefinition($locatorId)->setPublic(true);

        $container->compile();

        $locator = $container->get($locatorId)->get('test:command');
        $this->assertInstanceOf(NullLogger::class, $locator->get('logger'));
    }

    public function testProcessCompositeTypeArguments()
    {
        $container = new ContainerBuilder();
        $container->register('console.argument_resolver.service')->addArgument(null);

        $command = new Definition(CommandWithCompositeTypes::class);
        $command->addTag('console.command', ['command' => 'test:command']);
        $command->addTag('console.command.service_arguments');
        $container->setDefinition('test.command', $command);

        $pass = new RegisterCommandArgumentLocatorsPass();
        $pass->process($container);

        $serviceResolverDef = $container->getDefinition('console.argument_resolver.service');
        $commands = $container->getDefinition((string) $serviceResolverDef->getArgument(0))->getArgument(0);
        $locator = $container->getDefinition((string) $commands['test:command']->getValues()[0]);
        $locator = $container->getDefinition((string) $locator->getFactory()[0]);

        $expected = [
            'intersection' => new ServiceClosureArgument(new TypedReference($type = CombinedTypeA::class.'&'.CombinedTypeB::class, $type, ContainerInterface::RUNTIME_EXCEPTION_ON_INVALID_REFERENCE, 'intersection')),
            'union' => new ServiceClosureArgument(new TypedReference($type = CombinedTypeA::class.'|'.CombinedTypeB::class, $type, ContainerInterface::RUNTIME_EXCEPTION_ON_INVALID_REFERENCE, 'union')),
            'nullableIntersection' => new ServiceClosureArgument(new TypedReference($type = '('.CombinedTypeA::class.'&'.CombinedTypeB::class.')|null', $type, ContainerInterface::IGNORE_ON_INVALID_REFERENCE, 'nullableIntersection')),
        ];
        $this->assertEquals($expected, $locator->getArgument(0));
    }

    public function testProcessCompositeTypesAreAutowired()
    {
        $container = new ContainerBuilder();
        $resolver = $container->register('console.argument_resolver.service', 'stdClass')->addArgument(null);

        $container->register('combined', CombinedTypeService::class);
        $container->setAlias(CombinedTypeA::class, 'combined');
        $container->setAlias(CombinedTypeB::class, 'combined');

        $command = new Definition(CommandWithCompositeTypes::class);
        $command->addTag('console.command', ['command' => 'test:command']);
        $command->addTag('console.command.service_arguments');
        $container->setDefinition('test.command', $command);

        $pass = new RegisterCommandArgumentLocatorsPass();
        $pass->process($container);

        $locatorId = (string) $resolver->getArgument(0);
        $container->getDefinition($locatorId)->setPublic(true);

        $container->compile();

        $locator = $container->get($locatorId)->get('test:command');

        $this->assertInstanceOf(CombinedTypeService::class, $locator->get('intersection'));
        $this->assertInstanceOf(CombinedTypeService::class, $locator->get('union'));
        $this->assertInstanceOf(CombinedTypeService::class, $locator->get('nullableIntersection'));
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

class CommandWithAutowireAttribute
{
    public function __invoke(
        #[Autowire(service: 'logger')]
        LoggerInterface $logger,
    ) {
    }
}

class CommandWithAutowireAttributeInvalidReference
{
    public function __invoke(
        #[Autowire(service: 'invalid.id')]
        \stdClass $service2,
    ) {
    }
}

class CommandWithAutowireAttributeNullableInvalidReference
{
    public function __invoke(
        #[Autowire(service: 'invalid.id')]
        ?\stdClass $service2 = null,
    ) {
    }
}

class CommandWithTarget
{
    public function __invoke(
        #[Target('request.logger')]
        LoggerInterface $logger,
    ): void {
    }
}

interface CombinedTypeA
{
}

interface CombinedTypeB
{
}

class CombinedTypeService implements CombinedTypeA, CombinedTypeB
{
}

class CommandWithCompositeTypes
{
    public function __invoke(
        CombinedTypeA&CombinedTypeB $intersection,
        CombinedTypeA|CombinedTypeB $union,
        (CombinedTypeA&CombinedTypeB)|null $nullableIntersection,
    ): void {
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
