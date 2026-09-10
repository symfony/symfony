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
use Symfony\Component\DependencyInjection\Compiler\RemoveMissingDependenciesPass;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Exception\InvalidArgumentException;

class RemoveMissingDependenciesPassTest extends TestCase
{
    public function testTheDefinitionStaysWhenTheServiceIsThere()
    {
        $container = new ContainerBuilder();
        $container->register('profiler');
        $container->register('data_collector.translation')
            ->addTag('container.remove_if_missing', ['service' => 'profiler']);

        $this->process($container);

        $this->assertTrue($container->hasDefinition('data_collector.translation'));
    }

    public function testTheDefinitionGoesWhenTheServiceIsMissing()
    {
        $container = new ContainerBuilder();
        $container->register('data_collector.translation')
            ->addTag('container.remove_if_missing', ['service' => 'profiler']);

        $this->process($container);

        $this->assertFalse($container->hasDefinition('data_collector.translation'));
    }

    public function testAnAliasSatisfiesTheCondition()
    {
        $container = new ContainerBuilder();
        $container->register('property_accessor');
        $container->setAlias('serializer.property_accessor', 'property_accessor');
        $container->register('serializer.normalizer.object')
            ->addTag('container.remove_if_missing', ['service' => 'serializer.property_accessor']);

        $this->process($container);

        $this->assertTrue($container->hasDefinition('serializer.normalizer.object'));
    }

    public function testAnAliasWithNoTargetDoesNotSatisfyTheCondition()
    {
        $container = new ContainerBuilder();
        $container->setAlias('serializer.property_accessor', 'property_accessor');
        $container->register('serializer.normalizer.object')
            ->addTag('container.remove_if_missing', ['service' => 'serializer.property_accessor']);

        $this->process($container);

        $this->assertFalse($container->hasDefinition('serializer.normalizer.object'), 'the container answers true for a dangling alias, so the pass follows it');
    }

    public function testTheTagsOfADefinitionAreAllRequired()
    {
        $container = new ContainerBuilder();
        $container->register('workflow.registry');
        $container->register('twig.extension.workflow')
            ->addTag('container.remove_if_missing', ['service' => 'workflow.registry'])
            ->addTag('container.remove_if_missing', ['class' => 'Vendor\Absent\Workflow']);

        $this->process($container);

        $this->assertFalse($container->hasDefinition('twig.extension.workflow'));
    }

    public function testAClassCondition()
    {
        $container = new ContainerBuilder();
        $container->register('here')->addTag('container.remove_if_missing', ['class' => self::class]);
        $container->register('gone')->addTag('container.remove_if_missing', ['class' => 'Vendor\Absent\Thing']);

        $this->process($container);

        $this->assertTrue($container->hasDefinition('here'));
        $this->assertFalse($container->hasDefinition('gone'));
    }

    public function testAPackageCondition()
    {
        $container = new ContainerBuilder();
        $container->register('here')
            ->addTag('container.remove_if_missing', ['package' => 'symfony/dependency-injection', 'class' => ContainerBuilder::class]);
        $container->register('gone')
            ->addTag('container.remove_if_missing', ['package' => 'vendor/absent', 'class' => 'Vendor\Absent\Thing']);

        $this->process($container);

        $this->assertTrue($container->hasDefinition('here'));
        $this->assertFalse($container->hasDefinition('gone'));
    }

    public function testARemovalCascades()
    {
        $container = new ContainerBuilder();
        $container->register('a')->addTag('container.remove_if_missing', ['service' => 'missing']);
        $container->register('b')->addTag('container.remove_if_missing', ['service' => 'a']);
        $container->register('c')->addTag('container.remove_if_missing', ['service' => 'b']);

        $this->process($container);

        $this->assertFalse($container->hasDefinition('a'));
        $this->assertFalse($container->hasDefinition('b'), 'b went with a');
        $this->assertFalse($container->hasDefinition('c'), 'c went with b');
    }

    public function testTheAliasesOfARemovedDefinitionGoToo()
    {
        $container = new ContainerBuilder();
        $container->register('psr18.http_client')->addTag('container.remove_if_missing', ['service' => 'missing']);
        $container->setAlias('Psr\Http\Client\ClientInterface', 'psr18.http_client');
        $container->setAlias('psr18.alias.of.alias', 'Psr\Http\Client\ClientInterface');

        $this->process($container);

        $this->assertFalse($container->hasAlias('Psr\Http\Client\ClientInterface'));
        $this->assertFalse($container->hasAlias('psr18.alias.of.alias'));
    }

    public function testACircularConditionRemovesBoth()
    {
        $container = new ContainerBuilder();
        $container->register('a')->addTag('container.remove_if_missing', ['service' => 'b']);
        $container->register('b')->addTag('container.remove_if_missing', ['service' => 'a']);

        $this->process($container);

        $this->assertTrue($container->hasDefinition('a'), 'both are there, so both conditions hold');
        $this->assertTrue($container->hasDefinition('b'));
    }

    public function testAListOfServicesIsMetByAnyOfThem()
    {
        $container = new ContainerBuilder();
        $container->register('test.client');
        $container->register('mailer.message_logger_listener')
            ->addTag('container.remove_if_missing', ['service' => ['profiler', 'test.client']]);

        $this->process($container);

        $this->assertTrue($container->hasDefinition('mailer.message_logger_listener'), 'the test client alone keeps it');
    }

    public function testAListOfServicesIsUnmetWhenNoneIsThere()
    {
        $container = new ContainerBuilder();
        $container->register('mailer.message_logger_listener')
            ->addTag('container.remove_if_missing', ['service' => ['profiler', 'test.client']]);

        $this->process($container);

        $this->assertFalse($container->hasDefinition('mailer.message_logger_listener'));
        $this->assertSame(
            ['Symfony\\Component\\DependencyInjection\\Compiler\\RemoveMissingDependenciesPass: Removed service "mailer.message_logger_listener"; reason: none of the services "profiler", "test.client" is there.'],
            $container->getCompiler()->getLog()
        );
    }

    public function testTheCompilerLogSaysWhy()
    {
        $container = new ContainerBuilder();
        $container->register('data_collector.translation')
            ->addTag('container.remove_if_missing', ['service' => 'profiler']);
        $container->setAlias('alias.of.it', 'data_collector.translation');

        $this->process($container);

        $this->assertSame([
            'Symfony\\Component\\DependencyInjection\\Compiler\\RemoveMissingDependenciesPass: Removed service "data_collector.translation"; reason: service "profiler" is missing.',
            'Symfony\\Component\\DependencyInjection\\Compiler\\RemoveMissingDependenciesPass: Removed service "alias.of.it"; reason: it aliases "data_collector.translation".',
        ], $container->getCompiler()->getLog());
    }

    public function testAnUnknownAttributeIsRejected()
    {
        $container = new ContainerBuilder();
        $container->register('a')->addTag('container.remove_if_missing', ['servicee' => 'b']);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('unknown attribute "servicee"');

        $this->process($container);
    }

    public function testAnEmptyConditionIsRejected()
    {
        $container = new ContainerBuilder();
        $container->register('a')->addTag('container.remove_if_missing', []);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('at least one of the "service" or "class" attributes is required');

        $this->process($container);
    }

    public function testAPackageWithoutAClassIsRejected()
    {
        $container = new ContainerBuilder();
        $container->register('a')->addTag('container.remove_if_missing', ['service' => 'b', 'package' => 'vendor/thing']);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('the "package" and "parent_packages" attributes require the "class" one');

        $this->process($container);
    }

    private function process(ContainerBuilder $container): void
    {
        new RemoveMissingDependenciesPass()->process($container);
    }
}
