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
use Symfony\Component\DependencyInjection\Compiler\ResolveInstanceofConditionalsPass;
use Symfony\Component\DependencyInjection\Compiler\ResolveDefinitionTemplatesPass;
use Symfony\Component\DependencyInjection\Compiler\ResolveTagsInheritancePass;
use Symfony\Component\DependencyInjection\ContainerBuilder;

class ResolveInstanceofConditionalsPassTest extends TestCase
{
    public function testProcess()
    {
        $container = new ContainerBuilder();
        $def = $container->register('foo', self::class)->addTag('tag')->setAutowired(true)->setChanges(array());
        $def->setInstanceofConditionals(array(
            parent::class => (new ChildDefinition(''))->setProperty('foo', 'bar')->addTag('baz', array('attr' => 123)),
        ));

        (new ResolveInstanceofConditionalsPass())->process($container);

        $parent = 'instanceof.'.parent::class.'.0.foo';
        $def = $container->getDefinition('foo');
        $this->assertEmpty($def->getInstanceofConditionals());
        $this->assertInstanceof(ChildDefinition::class, $def);
        $this->assertTrue($def->isAutowired());
        $this->assertFalse($def->getInheritTags());
        $this->assertSame($parent, $def->getParent());
        $this->assertSame(array('tag' => array(array()), 'baz' => array(array('attr' => 123))), $def->getTags());

        $parent = $container->getDefinition($parent);
        $this->assertSame(array('foo' => 'bar'), $parent->getProperties());
        $this->assertSame(array(), $parent->getTags());
    }

    public function testProcessInheritance()
    {
        $container = new ContainerBuilder();

        $def = $container
            ->register('parent', parent::class)
            ->addMethodCall('foo', array('foo'));
        $def->setInstanceofConditionals(array(
            parent::class => (new ChildDefinition(''))->addMethodCall('foo', array('bar')),
        ));

        $def = (new ChildDefinition('parent'))->setClass(self::class);
        $def->setInstanceofConditionals(array(
            parent::class => (new ChildDefinition(''))->addMethodCall('foo', array('baz')),
        ));
        $container->setDefinition('child', $def);

        (new ResolveInstanceofConditionalsPass())->process($container);
        (new ResolveDefinitionTemplatesPass())->process($container);

        $expected = array(
            array('foo', array('bar')),
            array('foo', array('foo')),
        );

        $this->assertSame($expected, $container->getDefinition('parent')->getMethodCalls());
        $this->assertSame($expected, $container->getDefinition('child')->getMethodCalls());
    }

    public function testProcessDoesReplaceShared()
    {
        $container = new ContainerBuilder();

        $def = $container->register('foo', 'stdClass');
        $def->setInstanceofConditionals(array(
            'stdClass' => (new ChildDefinition(''))->setShared(false),
        ));

        (new ResolveInstanceofConditionalsPass())->process($container);

        $def = $container->getDefinition('foo');
        $this->assertFalse($def->isShared());
    }

    public function testProcessHandlesMultipleInheritance()
    {
        $container = new ContainerBuilder();

        $def = $container->register('foo', self::class)->setShared(true);

        $def->setInstanceofConditionals(array(
            parent::class => (new ChildDefinition(''))->setLazy(true)->setShared(false),
            self::class => (new ChildDefinition(''))->setAutowired(true),
        ));

        (new ResolveInstanceofConditionalsPass())->process($container);
        (new ResolveDefinitionTemplatesPass())->process($container);

        $def = $container->getDefinition('foo');
        $this->assertTrue($def->isAutowired());
        $this->assertTrue($def->isLazy());
        $this->assertTrue($def->isShared());
    }

    public function testProcessUsesAutomaticInstanceofDefinitions()
    {
        $container = new ContainerBuilder();
        $def = $container->register('normal_service', self::class);
        $def->setInstanceofConditionals(array(
            parent::class => (new ChildDefinition(''))
                ->addTag('local_instanceof_tag')
                ->setFactory('locally_set_factory'),
        ));
        $def->setAutoconfigured(true);
        $container->registerForAutoconfiguration(parent::class)
            ->addTag('automatic_instanceof_tag')
            ->setAutowired(true)
            ->setFactory('automatically_set_factory');

        (new ResolveInstanceofConditionalsPass())->process($container);
        (new ResolveTagsInheritancePass())->process($container);
        (new ResolveDefinitionTemplatesPass())->process($container);

        $def = $container->getDefinition('normal_service');
        // autowired thanks to the automatic instanceof
        $this->assertTrue($def->isAutowired());
        // factory from the specific instanceof overrides global one
        $this->assertEquals('locally_set_factory', $def->getFactory());
        // tags are merged, the locally set one is first
        $this->assertSame(array('local_instanceof_tag' => array(array()), 'automatic_instanceof_tag' => array(array())), $def->getTags());
    }

    public function testProcessDoesNotUseAutomaticInstanceofDefinitionsIfNotEnabled()
    {
        $container = new ContainerBuilder();
        $def = $container->register('normal_service', self::class);
        $def->setInstanceofConditionals(array(
            parent::class => (new ChildDefinition(''))
                ->addTag('foo_tag'),
        ));
        $container->registerForAutoconfiguration(parent::class)
            ->setAutowired(true);

        (new ResolveInstanceofConditionalsPass())->process($container);
        (new ResolveDefinitionTemplatesPass())->process($container);

        $def = $container->getDefinition('normal_service');
        // no automatic_tag, it was not enabled on the Definition
        $this->assertFalse($def->isAutowired());
    }
}
