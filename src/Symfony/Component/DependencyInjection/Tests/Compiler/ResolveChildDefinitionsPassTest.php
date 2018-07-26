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
use Symfony\Component\DependencyInjection\Compiler\ResolveChildDefinitionsPass;
use Symfony\Component\DependencyInjection\ContainerBuilder;

class ResolveChildDefinitionsPassTest extends TestCase
{
    public function testProcess()
    {
        $container = new ContainerBuilder();
        $container->register('parent', 'foo')->setArguments(array('moo', 'b'))->setProperty('foo', 'moo');
        $container->setDefinition('child', new ChildDefinition('parent'))
            ->replaceArgument(0, 'a')
            ->setProperty('foo', 'bar')
            ->setClass('bar')
        ;

        $this->process($container);

        $def = $container->getDefinition('child');
        $this->assertNotInstanceOf(ChildDefinition::class, $def);
        $this->assertEquals('bar', $def->getClass());
        $this->assertEquals(array('a', 'b'), $def->getArguments());
        $this->assertEquals(array('foo' => 'bar'), $def->getProperties());
    }

    public function testProcessAppendsMethodCallsAlways()
    {
        $container = new ContainerBuilder();

        $container
            ->register('parent')
            ->addMethodCall('foo', array('bar'))
        ;

        $container
            ->setDefinition('child', new ChildDefinition('parent'))
            ->addMethodCall('bar', array('foo'))
        ;

        $this->process($container);

        $def = $container->getDefinition('child');
        $this->assertEquals(array(
            array('foo', array('bar')),
            array('bar', array('foo')),
        ), $def->getMethodCalls());
    }

    public function testProcessDoesNotCopyAbstract()
    {
        $container = new ContainerBuilder();

        $container
            ->register('parent')
            ->setAbstract(true)
        ;

        $container
            ->setDefinition('child', new ChildDefinition('parent'))
        ;

        $this->process($container);

        $def = $container->getDefinition('child');
        $this->assertFalse($def->isAbstract());
    }

    public function testProcessDoesNotCopyShared()
    {
        $container = new ContainerBuilder();

        $container
            ->register('parent')
            ->setShared(false)
        ;

        $container
            ->setDefinition('child', new ChildDefinition('parent'))
        ;

        $this->process($container);

        $def = $container->getDefinition('child');
        $this->assertTrue($def->isShared());
    }

    public function testProcessDoesNotCopyTags()
    {
        $container = new ContainerBuilder();

        $container
            ->register('parent')
            ->addTag('foo')
        ;

        $container
            ->setDefinition('child', new ChildDefinition('parent'))
        ;

        $this->process($container);

        $def = $container->getDefinition('child');
        $this->assertEquals(array(), $def->getTags());
    }

    public function testProcessDoesNotCopyDecoratedService()
    {
        $container = new ContainerBuilder();

        $container
            ->register('parent')
            ->setDecoratedService('foo')
        ;

        $container
            ->setDefinition('child', new ChildDefinition('parent'))
        ;

        $this->process($container);

        $def = $container->getDefinition('child');
        $this->assertNull($def->getDecoratedService());
    }

    public function testProcessDoesNotDropShared()
    {
        $container = new ContainerBuilder();

        $container
            ->register('parent')
        ;

        $container
            ->setDefinition('child', new ChildDefinition('parent'))
            ->setShared(false)
        ;

        $this->process($container);

        $def = $container->getDefinition('child');
        $this->assertFalse($def->isShared());
    }

    public function testProcessHandlesMultipleInheritance()
    {
        $container = new ContainerBuilder();

        $container
            ->register('parent', 'foo')
            ->setArguments(array('foo', 'bar', 'c'))
        ;

        $container
            ->setDefinition('child2', new ChildDefinition('child1'))
            ->replaceArgument(1, 'b')
        ;

        $container
            ->setDefinition('child1', new ChildDefinition('parent'))
            ->replaceArgument(0, 'a')
        ;

        $this->process($container);

        $def = $container->getDefinition('child2');
        $this->assertEquals(array('a', 'b', 'c'), $def->getArguments());
        $this->assertEquals('foo', $def->getClass());
    }

    public function testSetLazyOnServiceHasParent()
    {
        $container = new ContainerBuilder();

        $container->register('parent', 'stdClass');

        $container->setDefinition('child1', new ChildDefinition('parent'))
            ->setLazy(true)
        ;

        $this->process($container);

        $this->assertTrue($container->getDefinition('child1')->isLazy());
    }

    public function testSetLazyOnServiceIsParent()
    {
        $container = new ContainerBuilder();

        $container->register('parent', 'stdClass')
            ->setLazy(true)
        ;

        $container->setDefinition('child1', new ChildDefinition('parent'));

        $this->process($container);

        $this->assertTrue($container->getDefinition('child1')->isLazy());
    }

    public function testSetAutowiredOnServiceHasParent()
    {
        $container = new ContainerBuilder();

        $container->register('parent', 'stdClass')
            ->setAutowired(true)
        ;

        $container->setDefinition('child1', new ChildDefinition('parent'))
            ->setAutowired(false)
        ;

        $this->process($container);

        $this->assertFalse($container->getDefinition('child1')->isAutowired());
    }

    public function testSetAutowiredOnServiceIsParent()
    {
        $container = new ContainerBuilder();

        $container->register('parent', 'stdClass')
            ->setAutowired(true)
        ;

        $container->setDefinition('child1', new ChildDefinition('parent'));

        $this->process($container);

        $this->assertTrue($container->getDefinition('child1')->isAutowired());
    }

    public function testDeepDefinitionsResolving()
    {
        $container = new ContainerBuilder();

        $container->register('parent', 'parentClass');
        $container->register('sibling', 'siblingClass')
            ->setConfigurator(new ChildDefinition('parent'), 'foo')
            ->setFactory(array(new ChildDefinition('parent'), 'foo'))
            ->addArgument(new ChildDefinition('parent'))
            ->setProperty('prop', new ChildDefinition('parent'))
            ->addMethodCall('meth', array(new ChildDefinition('parent')))
        ;

        $this->process($container);

        $configurator = $container->getDefinition('sibling')->getConfigurator();
        $this->assertSame('Symfony\Component\DependencyInjection\Definition', \get_class($configurator));
        $this->assertSame('parentClass', $configurator->getClass());

        $factory = $container->getDefinition('sibling')->getFactory();
        $this->assertSame('Symfony\Component\DependencyInjection\Definition', \get_class($factory[0]));
        $this->assertSame('parentClass', $factory[0]->getClass());

        $argument = $container->getDefinition('sibling')->getArgument(0);
        $this->assertSame('Symfony\Component\DependencyInjection\Definition', \get_class($argument));
        $this->assertSame('parentClass', $argument->getClass());

        $properties = $container->getDefinition('sibling')->getProperties();
        $this->assertSame('Symfony\Component\DependencyInjection\Definition', \get_class($properties['prop']));
        $this->assertSame('parentClass', $properties['prop']->getClass());

        $methodCalls = $container->getDefinition('sibling')->getMethodCalls();
        $this->assertSame('Symfony\Component\DependencyInjection\Definition', \get_class($methodCalls[0][1][0]));
        $this->assertSame('parentClass', $methodCalls[0][1][0]->getClass());
    }

    public function testSetDecoratedServiceOnServiceHasParent()
    {
        $container = new ContainerBuilder();

        $container->register('parent', 'stdClass');

        $container->setDefinition('child1', new ChildDefinition('parent'))
            ->setDecoratedService('foo', 'foo_inner', 5)
        ;

        $this->process($container);

        $this->assertEquals(array('foo', 'foo_inner', 5), $container->getDefinition('child1')->getDecoratedService());
    }

    public function testDecoratedServiceCopiesDeprecatedStatusFromParent()
    {
        $container = new ContainerBuilder();
        $container->register('deprecated_parent')
            ->setDeprecated(true)
        ;

        $container->setDefinition('decorated_deprecated_parent', new ChildDefinition('deprecated_parent'));

        $this->process($container);

        $this->assertTrue($container->getDefinition('decorated_deprecated_parent')->isDeprecated());
    }

    public function testDecoratedServiceCanOverwriteDeprecatedParentStatus()
    {
        $container = new ContainerBuilder();
        $container->register('deprecated_parent')
            ->setDeprecated(true)
        ;

        $container->setDefinition('decorated_deprecated_parent', new ChildDefinition('deprecated_parent'))
            ->setDeprecated(false)
        ;

        $this->process($container);

        $this->assertFalse($container->getDefinition('decorated_deprecated_parent')->isDeprecated());
    }

    public function testProcessResolvesAliases()
    {
        $container = new ContainerBuilder();

        $container->register('parent', 'ParentClass');
        $container->setAlias('parent_alias', 'parent');
        $container->setDefinition('child', new ChildDefinition('parent_alias'));

        $this->process($container);

        $def = $container->getDefinition('child');
        $this->assertSame('ParentClass', $def->getClass());
    }

    public function testProcessSetsArguments()
    {
        $container = new ContainerBuilder();

        $container->register('parent', 'ParentClass')->setArguments(array(0));
        $container->setDefinition('child', (new ChildDefinition('parent'))->setArguments(array(
            1,
            'index_0' => 2,
            'foo' => 3,
        )));

        $this->process($container);

        $def = $container->getDefinition('child');
        $this->assertSame(array(2, 1, 'foo' => 3), $def->getArguments());
    }

    public function testBindings()
    {
        $container = new ContainerBuilder();

        $container->register('parent', 'stdClass')
            ->setBindings(array('a' => '1', 'b' => '2'))
        ;

        $child = $container->setDefinition('child', new ChildDefinition('parent'))
            ->setBindings(array('b' => 'B', 'c' => 'C'))
        ;

        $this->process($container);

        $bindings = array();
        foreach ($container->getDefinition('child')->getBindings() as $k => $v) {
            $bindings[$k] = $v->getValues()[0];
        }
        $this->assertEquals(array('b' => 'B', 'c' => 'C', 'a' => '1'), $bindings);
    }

    public function testSetAutoconfiguredOnServiceIsParent()
    {
        $container = new ContainerBuilder();

        $container->register('parent', 'stdClass')
            ->setAutoconfigured(true)
        ;

        $container->setDefinition('child1', new ChildDefinition('parent'));

        $this->process($container);

        $this->assertFalse($container->getDefinition('child1')->isAutoconfigured());
    }

    protected function process(ContainerBuilder $container)
    {
        $pass = new ResolveChildDefinitionsPass();
        $pass->process($container);
    }
}
