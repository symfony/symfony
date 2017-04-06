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
use Symfony\Component\DependencyInjection\Alias;
use Symfony\Component\DependencyInjection\ChildDefinition;
use Symfony\Component\DependencyInjection\Definition;
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

    /**
     * Tests that instanceof config applies through parent-child service definitions.
     *
     * This test involves multiple compiler passes.
     */
    public function testConfigurationOverridePriority()
    {
        $container = new ContainerBuilder();
        $container->setResourceTracking(false);

        $parentDef = $container->register('parent', self::class);
        $container->setAlias('parent_alias', 'parent');
        $def = new ChildDefinition('parent_alias');
        $container->setDefinition('child', $def);

        $parentDef
            // overrides instanceof below
            ->setConfigurator('parent_configurator')
            ->addTag('foo', array('foo_tag_attr' => 'bar'))
            ->addTag('bar');

        $def
            ->setInheritTags(true)
            // overrides instanceof below
            ->setAutowired(true)
            ->setInstanceofConditionals(array(
            parent::class => (new Definition())
                ->setLazy(true)
                // both autowired and configurator are overridden
                ->setAutowired(false)
                ->setConfigurator('instanceof_configurator')
                ->addTag('foo')
                ->addTag('baz'),
        ));

        $container->compile();

        // instanceof sets this
        $childDef = $container->getDefinition('child');
        $this->assertTrue($childDef->isLazy());
        $this->assertTrue($childDef->isAutowired());
        $this->assertEquals('parent_configurator', $childDef->getConfigurator());
        $this->assertSame(
            array(
                // foo tag on service (parent) overrides instanceof
                'foo' => array(array('foo_tag_attr' => 'bar')),
                'bar' => array(array()),
                'baz' => array(array()),
            ),
            $container->getDefinition('child')->getTags()
        );
        $this->assertFalse($container->hasDefinition('c'), 'Service C was not inlined.');
    }
}
