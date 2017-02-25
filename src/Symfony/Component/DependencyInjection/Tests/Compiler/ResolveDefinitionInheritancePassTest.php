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
        $def = $container->register('parent', self::class)->setArguments(array('moo', 'b'))->setProperty('foo', 'moo');
        $def->setInstanceofConditionals(array(
            parent::class => (new ChildDefinition(''))
                ->replaceArgument(0, 'a')
                ->setProperty('foo', 'bar')
                ->setClass('bar'),
        ));

        $this->process($container);

        $this->assertEmpty($def->getInstanceofConditionals());
        $this->assertSame($def, $container->getDefinition('parent'));
        $this->assertEquals('bar', $def->getClass());
        $this->assertEquals(array('a', 'b'), $def->getArguments());
        $this->assertEquals(array('foo' => 'bar'), $def->getProperties());
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

    public function testProcessDoesReplaceAbstract()
    {
        $container = new ContainerBuilder();

        $def = $container->register('parent', 'stdClass');

        $def->setInstanceofConditionals(array(
            'stdClass' => (new ChildDefinition(''))->setAbstract(true),
        ));

        $this->process($container);

        $this->assertTrue($def->isAbstract());
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
            ->setArguments(array('foo', 'bar', 'c'))
        ;

        $def->setInstanceofConditionals(array(
            parent::class => (new ChildDefinition(''))->replaceArgument(1, 'b'),
            self::class => (new ChildDefinition(''))->replaceArgument(0, 'a'),
        ));

        $this->process($container);

        $this->assertEquals(array('a', 'b', 'c'), $def->getArguments());
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

        $container->register('parent', self::class)->addTag('parent');

        $def = $container->setDefinition('child', new ChildDefinition('parent'))
            ->addTag('child')
            ->setInheritTags(true)
        ;

        $def->setInstanceofConditionals(array(
            parent::class => (new ChildDefinition(''))->addTag('foo'),
        ));

        $this->process($container);

        $t = array(array());
        $this->assertSame(array('foo' => $t, 'child' => $t, 'parent' => $t), $def->getTags());
    }

    public function testProcessResolvesAliasesAndTags()
    {
        $container = new ContainerBuilder();

        $container->register('parent', self::class);
        $container->setAlias('parent_alias', 'parent');
        $def = $container->setDefinition('child', new ChildDefinition('parent_alias'));
        $def->setInstanceofConditionals(array(
            parent::class => (new ChildDefinition(''))->addTag('foo'),
        ));

        $this->process($container);

        $this->assertSame(array('foo' => array(array())), $def->getTags());
        $this->assertSame($def, $container->getDefinition('child'));
        $this->assertEmpty($def->getClass());
    }

    protected function process(ContainerBuilder $container)
    {
        $pass = new ResolveDefinitionInheritancePass();
        $pass->process($container);
    }
}
