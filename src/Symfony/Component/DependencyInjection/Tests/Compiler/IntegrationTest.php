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
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\Alias;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\DependencyInjection\ServiceSubscriberInterface;

/**
 * This class tests the integration of the different compiler passes.
 */
class IntegrationTest extends TestCase
{
    /**
     * This tests that dependencies are correctly processed.
     *
     * We're checking that:
     *
     *   * A is public, B/C are private
     *   * A -> C
     *   * B -> C
     */
    public function testProcessRemovesAndInlinesRecursively()
    {
        $container = new ContainerBuilder();
        $container->setResourceTracking(false);

        $a = $container
            ->register('a', '\stdClass')
            ->addArgument(new Reference('c'))
            ->setPublic(true)
        ;

        $b = $container
            ->register('b', '\stdClass')
            ->addArgument(new Reference('c'))
            ->setPublic(false)
        ;

        $c = $container
            ->register('c', '\stdClass')
            ->setPublic(false)
        ;

        $container->compile();

        $this->assertTrue($container->hasDefinition('a'));
        $arguments = $a->getArguments();
        $this->assertSame($c, $arguments[0]);
        $this->assertFalse($container->hasDefinition('b'));
        $this->assertFalse($container->hasDefinition('c'));
    }

    public function testProcessInlinesReferencesToAliases()
    {
        $container = new ContainerBuilder();
        $container->setResourceTracking(false);

        $a = $container
            ->register('a', '\stdClass')
            ->addArgument(new Reference('b'))
            ->setPublic(true)
        ;

        $container->setAlias('b', new Alias('c', false));

        $c = $container
            ->register('c', '\stdClass')
            ->setPublic(false)
        ;

        $container->compile();

        $this->assertTrue($container->hasDefinition('a'));
        $arguments = $a->getArguments();
        $this->assertSame($c, $arguments[0]);
        $this->assertFalse($container->hasAlias('b'));
        $this->assertFalse($container->hasDefinition('c'));
    }

    public function testProcessInlinesWhenThereAreMultipleReferencesButFromTheSameDefinition()
    {
        $container = new ContainerBuilder();
        $container->setResourceTracking(false);

        $container
            ->register('a', '\stdClass')
            ->addArgument(new Reference('b'))
            ->addMethodCall('setC', [new Reference('c')])
            ->setPublic(true)
        ;

        $container
            ->register('b', '\stdClass')
            ->addArgument(new Reference('c'))
            ->setPublic(false)
        ;

        $container
            ->register('c', '\stdClass')
            ->setPublic(false)
        ;

        $container->compile();

        $this->assertTrue($container->hasDefinition('a'));
        $this->assertFalse($container->hasDefinition('b'));
        $this->assertFalse($container->hasDefinition('c'), 'Service C was not inlined.');
    }

    public function testCanDecorateServiceSubscriber()
    {
        $container = new ContainerBuilder();
        $container->register(ServiceSubscriberStub::class)
            ->addTag('container.service_subscriber')
            ->setPublic(true);

        $container->register(DecoratedServiceSubscriber::class)
            ->setDecoratedService(ServiceSubscriberStub::class);

        $container->compile();

        $this->assertInstanceOf(DecoratedServiceSubscriber::class, $container->get(ServiceSubscriberStub::class));
    }

    /**
     * @dataProvider getYamlCompileTests
     */
    public function testYamlContainerCompiles($directory, $actualServiceId, $expectedServiceId, ContainerBuilder $mainContainer = null)
    {
        // allow a container to be passed in, which might have autoconfigure settings
        $container = $mainContainer ?: new ContainerBuilder();
        $container->setResourceTracking(false);
        $loader = new YamlFileLoader($container, new FileLocator(__DIR__.'/../Fixtures/yaml/integration/'.$directory));
        $loader->load('main.yml');
        $container->compile();
        $actualService = $container->getDefinition($actualServiceId);

        // create a fresh ContainerBuilder, to avoid autoconfigure stuff
        $container = new ContainerBuilder();
        $container->setResourceTracking(false);
        $loader = new YamlFileLoader($container, new FileLocator(__DIR__.'/../Fixtures/yaml/integration/'.$directory));
        $loader->load('expected.yml');
        $container->compile();
        $expectedService = $container->getDefinition($expectedServiceId);

        // reset changes, we don't care if these differ
        $actualService->setChanges([]);
        $expectedService->setChanges([]);

        $this->assertEquals($expectedService, $actualService);
    }

    public function getYamlCompileTests()
    {
        $container = new ContainerBuilder();
        $container->registerForAutoconfiguration(IntegrationTestStub::class);
        yield [
            'autoconfigure_child_not_applied',
            'child_service',
            'child_service_expected',
            $container,
        ];

        $container = new ContainerBuilder();
        $container->registerForAutoconfiguration(IntegrationTestStub::class);
        yield [
            'autoconfigure_parent_child',
            'child_service',
            'child_service_expected',
            $container,
        ];

        $container = new ContainerBuilder();
        $container->registerForAutoconfiguration(IntegrationTestStub::class)
            ->addTag('from_autoconfigure');
        yield [
            'autoconfigure_parent_child_tags',
            'child_service',
            'child_service_expected',
            $container,
        ];

        yield [
            'child_parent',
            'child_service',
            'child_service_expected',
        ];

        yield [
            'defaults_child_tags',
            'child_service',
            'child_service_expected',
        ];

        yield [
            'defaults_instanceof_importance',
            'main_service',
            'main_service_expected',
        ];

        yield [
            'defaults_parent_child',
            'child_service',
            'child_service_expected',
        ];

        yield [
            'instanceof_parent_child',
            'child_service',
            'child_service_expected',
        ];

        $container = new ContainerBuilder();
        $container->registerForAutoconfiguration(IntegrationTestStub::class)
            ->addMethodCall('setSunshine', ['supernova']);
        yield [
            'instanceof_and_calls',
            'main_service',
            'main_service_expected',
            $container,
        ];
    }
}

class ServiceSubscriberStub implements ServiceSubscriberInterface
{
    public static function getSubscribedServices()
    {
        return [];
    }
}

class DecoratedServiceSubscriber
{
}

class IntegrationTestStub extends IntegrationTestStubParent
{
}

class IntegrationTestStubParent
{
    public function enableSummer($enable)
    {
        // methods used in calls - added here to prevent errors for not existing
    }

    public function setSunshine($type)
    {
    }
}
