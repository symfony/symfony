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
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\DependencyInjection\ContainerBuilder;

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
            ->addMethodCall('setC', array(new Reference('c')))
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

    public function testInstanceofDefaultsAndParentDefinitionResolution()
    {
        $container = new ContainerBuilder();
        $container->setResourceTracking(false);

        // loading YAML with an expressive test-case in that file
        $loader = new YamlFileLoader($container, new FileLocator(__DIR__.'/../Fixtures/yaml'));
        $loader->load('services_defaults_instanceof_parent.yml');
        $container->compile();

        // instanceof overrides defaults
        $simpleService = $container->getDefinition('service_simple');
        $this->assertFalse($simpleService->isAutowired());
        $this->assertFalse($simpleService->isShared());

        // all tags are kept
        $this->assertEquals(
            array(
                'foo_tag' => array(array('tag_option' => 'from_service'), array('tag_option' => 'from_instanceof')),
                'bar_tag' => array(array()),
            ),
            $simpleService->getTags()
        );

        // calls are all kept, but service-level calls are last
        $this->assertEquals(
            array(
                // from instanceof
                array('setSunshine', array('bright')),
                // from service
                array('enableSummer', array(true)),
                array('setSunshine', array('warm')),
            ),
            $simpleService->getMethodCalls()
        );

        // service override instanceof
        $overrideService = $container->getDefinition('service_override_instanceof');
        $this->assertTrue($overrideService->isAutowired());

        // children definitions get no instanceof
        $childDef = $container->getDefinition('child_service');
        $this->assertEmpty($childDef->getTags());

        $childDef2 = $container->getDefinition('child_service_with_parent_instanceof');
        // taken from instanceof applied to parent
        $this->assertFalse($childDef2->isAutowired());
        // override the instanceof
        $this->assertTrue($childDef2->isShared());
        // tags inherit like normal
        $this->assertEquals(
            array(
                'foo_tag' => array(array('tag_option' => 'from_child_def'), array('tag_option' => 'from_parent_def'), array('tag_option' => 'from_instanceof')),
                'bar_tag' => array(array()),
            ),
            $childDef2->getTags()
        );
    }
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
