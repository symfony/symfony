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
                ->setProperty('foo', 'bar')
                ->setProperty('otherProp', 'baz')
        ));

        $this->process($container);

        $this->assertEmpty($def->getInstanceofConditionals());
        $this->assertSame($def, $container->getDefinition('parent'));
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

    protected function process(ContainerBuilder $container)
    {
        $pass = new ResolveDefinitionInheritancePass();
        $pass->process($container);
    }
}
