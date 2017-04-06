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
use Symfony\Component\DependencyInjection\ChildDefinition;
use Symfony\Component\DependencyInjection\Compiler\ResolveDefinitionInheritancePass;
use Symfony\Component\DependencyInjection\ContainerBuilder;

class ResolveDefinitionInheritancePassTest extends TestCase
{
    public function testProcess()
    {
        $container = new ContainerBuilder();
        $def = $container->register('parent', self::class)
            ->setProperty('foo', 'moo');
        $def->setInstanceofConditionals(array(
            parent::class => (new ChildDefinition(''))
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

    public function testProcessAppendsMethodCallsAlways()
    {
        $container = new ContainerBuilder();

        $def = $container
            ->register('parent', self::class)
            ->addMethodCall('foo', array('bar'));

        $def->setInstanceofConditionals(array(
                parent::class => (new ChildDefinition(''))
                    ->addMethodCall('bar', array('foo')),
        ));

        $this->process($container);

        $this->assertEquals(array(
            array('foo', array('bar')),
            array('bar', array('foo')),
        ), $container->getDefinition('parent')->getMethodCalls());
    }

    public function testProcessDoesReplaceShared()
    {
        $container = new ContainerBuilder();

        $def = $container->register('parent', 'stdClass');

        $def->setInstanceofConditionals(array(
            'stdClass' => (new ChildDefinition(''))->setShared(false),
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
            parent::class => (new ChildDefinition(''))->setProperty('bar', 'barval_changed'),
            self::class => (new ChildDefinition(''))->setProperty('foo', 'fooval_changed'),
        ));

        $this->process($container);

        $this->assertEquals(array('foo' => 'fooval', 'bar' => 'barval', 'baz' => 'bazval'), $def->getProperties());
    }

    public function testSetLazyOnServiceHasParent()
    {
        $container = new ContainerBuilder();

        $def = $container->register('parent', 'stdClass');

        $def->setInstanceofConditionals(array(
            'stdClass' => (new ChildDefinition(''))->setLazy(true),
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
            parent::class => (new ChildDefinition(''))
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
            parent::class => (new ChildDefinition(''))
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
