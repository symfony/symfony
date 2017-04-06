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
use Symfony\Component\DependencyInjection\Compiler\ResolveDefinitionInheritancePass;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;

class ResolveDefinitionInheritancePassTest extends TestCase
{
    public function testProcess()
    {
        $container = new ContainerBuilder();
        $def = $container->register('parent', self::class)
            ->setProperty('foo', 'moo');
        $def->setInstanceofConditionals(array(
            parent::class => (new Definition())
                ->setShared(false)
                ->setLazy(true)
                ->setPublic(false)
                ->setConfigurator('instanceof_configurator')
                ->addMethodCall('foo_call')
                ->addTag('foo_tag')
                ->setAutowired(true)
                ->setProperty('foo', 'bar')
                ->setProperty('otherProp', 'baz')
        ));

        $this->process($container);

        $this->assertEmpty($def->getInstanceofConditionals());
        $this->assertSame($def, $container->getDefinition('parent'));
        $this->assertFalse($def->isShared());
        $this->assertTrue($def->isLazy());
        $this->assertFalse($def->isPublic());
        $this->assertEquals('instanceof_configurator', $def->getConfigurator());
        $this->assertEquals(array(array('foo_call', array())), $def->getMethodCalls());
        $this->assertEquals(array('foo_tag' => array(array())), $def->getTags());
        $this->assertTrue($def->isAutowired());
        // foo property is not replaced, but otherProp is added
        $this->assertEquals(array('foo' => 'moo', 'otherProp' => 'baz'), $def->getProperties());
    }

    public function testProcessMergesMethodCallsAlways()
    {
        $container = new ContainerBuilder();

        $def = $container
            ->register('parent', self::class)
            ->addMethodCall('foo', array('bar'))
            ->addMethodCall('setBaz', array('sunshine_baz'));

        $def->setInstanceofConditionals(array(
                parent::class => (new Definition())
                    ->addMethodCall('bar', array('foo'))
                    ->addMethodCall('setBaz', array('rainbow_baz')),
        ));

        $this->process($container);

        $this->assertEquals(array(
            // instanceof call is first
            array('bar', array('foo')),
            array('foo', array('bar')),
            // because it has the same name, the definition overrides instanceof
            array('setBaz', array('sunshine_baz')),
        ), $container->getDefinition('parent')->getMethodCalls());
    }

    public function testProcessDoesReplaceShared()
    {
        $container = new ContainerBuilder();

        $def = $container->register('parent', 'stdClass');

        $def->setInstanceofConditionals(array(
            'stdClass' => (new Definition())->setShared(false),
        ));

        $this->process($container);

        $this->assertFalse($def->isShared());
    }

    public function testProcessHandlesMultipleInheritance()
    {
        $container = new ContainerBuilder();

        $def = $container
            ->register('parent', self::class)
            ->setProperties(array('foo' => 'fooval', 'bar' => 'barval', 'baz' => 'bazval'))
        ;

        $def->setInstanceofConditionals(array(
            parent::class => (new Definition())->setProperty('bar', 'barval_changed'),
            self::class => (new Definition())->setProperty('foo', 'fooval_changed'),
        ));

        $this->process($container);

        $this->assertEquals(array('foo' => 'fooval', 'bar' => 'barval', 'baz' => 'bazval'), $def->getProperties());
    }

    public function testSetLazyOnServiceHasParent()
    {
        $container = new ContainerBuilder();

        $def = $container->register('parent', 'stdClass');

        $def->setInstanceofConditionals(array(
            'stdClass' => (new Definition())->setLazy(true),
        ));

        $this->process($container);

        $this->assertTrue($container->getDefinition('parent')->isLazy());
    }

    public function testProcessInheritTags()
    {
        $container = new ContainerBuilder();

        $def = $container->register('parent', self::class)
            ->addTag('tag_foo')
            ->addTag('tag_bar', array('priority' => 100))
        ;

        $def->setInstanceofConditionals(array(
            parent::class => (new Definition())
                ->addTag('tag_bar', array('priority' => 500))
                ->addTag('tag_baz'),
        ));

        $this->process($container);

        $t = array(array());
        $this->assertSame(array('tag_foo' => $t, 'tag_bar' => array(array('priority' => 100)), 'tag_baz' => $t), $def->getTags());
    }

    /**
     * Tests that service configuration cascades in the correct order.
     *
     * For configuration: defaults > instanceof > service. In
     * other words, service-specific configuration is the strongest,
     * then instanceof and finally defaults.
     */
    public function testConfigurationOverridePriority()
    {
        $container = new ContainerBuilder();

        $def = $container->register('parent', self::class);
        // mimic how _defaults/defaults is loaded in YAML/XML
        $def
            ->setTrackChanges(false)
            ->setPublic(false)
            ->setAutowired(true)
            ->setTrackChanges(true);

        $def->setInstanceofConditionals(array(
            parent::class => (new Definition())
                // overrides autowired on _defaults
                ->setAutowired(false)
                ->setConfigurator('foo_configurator')
        ));

        $def
            // overrides public on _defaults
            ->setPublic(true)
            // overrides configurator on instanceof
            ->setConfigurator('bar_configurator')
        ;

        $this->process($container);

        // service-level wins over instanceof
        $this->assertTrue($def->isPublic());
        $this->assertEquals('bar_configurator', $def->getConfigurator());
        // instanceof-level wins over defaults
        $this->assertFalse($def->isAutowired());
    }

    private function process(ContainerBuilder $container)
    {
        $pass = new ResolveDefinitionInheritancePass();
        $pass->process($container);
    }
}
