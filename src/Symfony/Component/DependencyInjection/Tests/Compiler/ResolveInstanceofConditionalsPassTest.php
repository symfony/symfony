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
use Symfony\Component\Config\Resource\ResourceInterface;
use Symfony\Component\Config\ResourceCheckerInterface;
use Symfony\Component\DependencyInjection\Argument\BoundArgument;
use Symfony\Component\DependencyInjection\ChildDefinition;
use Symfony\Component\DependencyInjection\Compiler\ResolveChildDefinitionsPass;
use Symfony\Component\DependencyInjection\Compiler\ResolveInstanceofConditionalsPass;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Exception\InvalidArgumentException;
use Symfony\Component\DependencyInjection\Exception\RuntimeException;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Contracts\Service\ResetInterface;
use Symfony\Contracts\Service\ServiceSubscriberInterface;

class ResolveInstanceofConditionalsPassTest extends TestCase
{
    public function testProcess()
    {
        $container = new ContainerBuilder();
        $def = $container->register('foo', self::class)->addTag('tag')->setAutowired(true)->setChanges([]);
        $def->setInstanceofConditionals([
            parent::class => (new ChildDefinition(''))->setProperty('foo', 'bar')->addTag('baz', ['attr' => 123]),
        ]);

        (new ResolveInstanceofConditionalsPass())->process($container);

        $parent = '.instanceof.'.parent::class.'.0.foo';
        $def = $container->getDefinition('foo');
        self::assertEmpty($def->getInstanceofConditionals());
        self::assertInstanceOf(ChildDefinition::class, $def);
        self::assertTrue($def->isAutowired());
        self::assertSame($parent, $def->getParent());
        self::assertSame(['tag' => [[]], 'baz' => [['attr' => 123]]], $def->getTags());

        $parent = $container->getDefinition($parent);
        self::assertSame(['foo' => 'bar'], $parent->getProperties());
        self::assertSame([], $parent->getTags());
    }

    public function testProcessInheritance()
    {
        $container = new ContainerBuilder();

        $def = $container
            ->register('parent', parent::class)
            ->addMethodCall('foo', ['foo']);
        $def->setInstanceofConditionals([
            parent::class => (new ChildDefinition(''))->addMethodCall('foo', ['bar']),
        ]);

        $def = (new ChildDefinition('parent'))->setClass(self::class);
        $container->setDefinition('child', $def);

        (new ResolveInstanceofConditionalsPass())->process($container);
        (new ResolveChildDefinitionsPass())->process($container);

        $expected = [
            ['foo', ['bar']],
            ['foo', ['foo']],
        ];

        self::assertSame($expected, $container->getDefinition('parent')->getMethodCalls());
        self::assertSame($expected, $container->getDefinition('child')->getMethodCalls());
    }

    public function testProcessDoesReplaceShared()
    {
        $container = new ContainerBuilder();

        $def = $container->register('foo', 'stdClass');
        $def->setInstanceofConditionals([
            'stdClass' => (new ChildDefinition(''))->setShared(false),
        ]);

        (new ResolveInstanceofConditionalsPass())->process($container);

        $def = $container->getDefinition('foo');
        self::assertFalse($def->isShared());
    }

    public function testProcessHandlesMultipleInheritance()
    {
        $container = new ContainerBuilder();

        $def = $container->register('foo', self::class)->setShared(true);

        $def->setInstanceofConditionals([
            parent::class => (new ChildDefinition(''))->setLazy(true)->setShared(false),
            self::class => (new ChildDefinition(''))->setAutowired(true),
        ]);

        (new ResolveInstanceofConditionalsPass())->process($container);
        (new ResolveChildDefinitionsPass())->process($container);

        $def = $container->getDefinition('foo');
        self::assertTrue($def->isAutowired());
        self::assertTrue($def->isLazy());
        self::assertTrue($def->isShared());
    }

    public function testProcessUsesAutoconfiguredInstanceof()
    {
        $container = new ContainerBuilder();
        $def = $container->register('normal_service', self::class);
        $def->setInstanceofConditionals([
            parent::class => (new ChildDefinition(''))
                ->addTag('local_instanceof_tag')
                ->setFactory('locally_set_factory'),
        ]);
        $def->setAutoconfigured(true);
        $container->registerForAutoconfiguration(parent::class)
            ->addTag('autoconfigured_tag')
            ->setAutowired(true)
            ->setFactory('autoconfigured_factory');

        (new ResolveInstanceofConditionalsPass())->process($container);
        (new ResolveChildDefinitionsPass())->process($container);

        $def = $container->getDefinition('normal_service');
        // autowired thanks to the autoconfigured instanceof
        self::assertTrue($def->isAutowired());
        // factory from the specific instanceof overrides global one
        self::assertEquals('locally_set_factory', $def->getFactory());
        // tags are merged, the locally set one is first
        self::assertSame(['local_instanceof_tag' => [[]], 'autoconfigured_tag' => [[]]], $def->getTags());
    }

    public function testAutoconfigureInstanceofDoesNotDuplicateTags()
    {
        $container = new ContainerBuilder();
        $def = $container->register('normal_service', self::class);
        $def
            ->addTag('duplicated_tag')
            ->addTag('duplicated_tag', ['and_attributes' => 1])
        ;
        $def->setInstanceofConditionals([
            parent::class => (new ChildDefinition(''))->addTag('duplicated_tag'),
        ]);
        $def->setAutoconfigured(true);
        $container->registerForAutoconfiguration(parent::class)
            ->addTag('duplicated_tag', ['and_attributes' => 1])
        ;

        (new ResolveInstanceofConditionalsPass())->process($container);
        (new ResolveChildDefinitionsPass())->process($container);

        $def = $container->getDefinition('normal_service');
        self::assertSame(['duplicated_tag' => [[], ['and_attributes' => 1]]], $def->getTags());
    }

    public function testProcessDoesNotUseAutoconfiguredInstanceofIfNotEnabled()
    {
        $container = new ContainerBuilder();
        $def = $container->register('normal_service', self::class);
        $def->setInstanceofConditionals([
            parent::class => (new ChildDefinition(''))
                ->addTag('foo_tag'),
        ]);
        $container->registerForAutoconfiguration(parent::class)
            ->setAutowired(true);

        (new ResolveInstanceofConditionalsPass())->process($container);
        (new ResolveChildDefinitionsPass())->process($container);

        $def = $container->getDefinition('normal_service');
        self::assertFalse($def->isAutowired());
    }

    public function testBadInterfaceThrowsException()
    {
        self::expectException(RuntimeException::class);
        self::expectExceptionMessage('"App\FakeInterface" is set as an "instanceof" conditional, but it does not exist.');
        $container = new ContainerBuilder();
        $def = $container->register('normal_service', self::class);
        $def->setInstanceofConditionals([
            'App\\FakeInterface' => (new ChildDefinition(''))
                ->addTag('foo_tag'),
        ]);

        (new ResolveInstanceofConditionalsPass())->process($container);
    }

    public function testBadInterfaceForAutomaticInstanceofIsOk()
    {
        $container = new ContainerBuilder();
        $container->register('normal_service', self::class)
            ->setAutoconfigured(true);
        $container->registerForAutoconfiguration('App\\FakeInterface')
            ->setAutowired(true);

        (new ResolveInstanceofConditionalsPass())->process($container);
        self::assertTrue($container->hasDefinition('normal_service'));
    }

    /**
     * Test that autoconfigured calls are handled gracefully.
     */
    public function testProcessForAutoconfiguredCalls()
    {
        $container = new ContainerBuilder();

        $expected = [
            ['setFoo', [
                'plain_value',
                '%some_parameter%',
            ]],
            ['callBar', []],
            ['isBaz', []],
        ];

        $container->registerForAutoconfiguration(parent::class)->addMethodCall('setFoo', $expected[0][1]);
        $container->registerForAutoconfiguration(self::class)->addMethodCall('callBar');

        $def = $container->register('foo', self::class)->setAutoconfigured(true)->addMethodCall('isBaz');
        self::assertEquals([['isBaz', []]], $def->getMethodCalls(), 'Definition shouldn\'t have only one method call.');

        (new ResolveInstanceofConditionalsPass())->process($container);

        self::assertEquals($expected, $container->findDefinition('foo')->getMethodCalls());
    }

    public function testProcessThrowsExceptionForArguments()
    {
        self::expectException(InvalidArgumentException::class);
        self::expectExceptionMessageMatches('/Autoconfigured instanceof for type "PHPUnit[\\\\_]Framework[\\\\_]TestCase" defines arguments but these are not supported and should be removed\./');
        $container = new ContainerBuilder();
        $container->registerForAutoconfiguration(parent::class)
            ->addArgument('bar');

        (new ResolveInstanceofConditionalsPass())->process($container);
    }

    public function testMergeReset()
    {
        $container = new ContainerBuilder();

        $container
            ->register('bar', self::class)
            ->addArgument('a')
            ->addMethodCall('setB')
            ->setDecoratedService('foo')
            ->addTag('t')
            ->setInstanceofConditionals([
                parent::class => (new ChildDefinition(''))->addTag('bar'),
            ])
        ;

        (new ResolveInstanceofConditionalsPass())->process($container);

        $abstract = $container->getDefinition('.abstract.instanceof.bar');

        self::assertEmpty($abstract->getArguments());
        self::assertEmpty($abstract->getMethodCalls());
        self::assertNull($abstract->getDecoratedService());
        self::assertEmpty($abstract->getTags());
        self::assertTrue($abstract->isAbstract());
    }

    public function testProcessForAutoconfiguredBindings()
    {
        $container = new ContainerBuilder();

        $container->registerForAutoconfiguration(self::class)
            ->setBindings([
                '$foo' => new BoundArgument(234, false),
                parent::class => new BoundArgument(new Reference('foo'), false),
            ]);

        $container->register('foo', self::class)
            ->setAutoconfigured(true)
            ->setBindings(['$foo' => new BoundArgument(123, false)]);

        (new ResolveInstanceofConditionalsPass())->process($container);

        $expected = [
            '$foo' => new BoundArgument(123, false),
            parent::class => new BoundArgument(new Reference('foo'), false),
        ];
        self::assertEquals($expected, $container->findDefinition('foo')->getBindings());
    }

    public function testBindingsOnInstanceofConditionals()
    {
        $container = new ContainerBuilder();
        $def = $container->register('foo', self::class)->setBindings(['$toto' => 123]);
        $def->setInstanceofConditionals([parent::class => new ChildDefinition('')]);

        (new ResolveInstanceofConditionalsPass())->process($container);

        $bindings = $container->getDefinition('foo')->getBindings();
        self::assertSame(['$toto'], array_keys($bindings));
        self::assertInstanceOf(BoundArgument::class, $bindings['$toto']);
        self::assertSame(123, $bindings['$toto']->getValues()[0]);
    }

    public function testDecoratorsAreNotAutomaticallyTagged()
    {
        $container = new ContainerBuilder();

        $decorator = $container->register('decorator', self::class);
        $decorator->setDecoratedService('decorated');
        $decorator->setInstanceofConditionals([
            parent::class => (new ChildDefinition(''))->addTag('tag'),
        ]);
        $decorator->setAutoconfigured(true);
        $decorator->addTag('manual');

        $container->registerForAutoconfiguration(parent::class)
            ->addTag('tag')
        ;

        (new ResolveInstanceofConditionalsPass())->process($container);
        (new ResolveChildDefinitionsPass())->process($container);

        self::assertSame(['manual' => [[]]], $container->getDefinition('decorator')->getTags());
    }

    public function testDecoratorsKeepBehaviorDescribingTags()
    {
        $container = new ContainerBuilder();

        $container->setParameter('container.behavior_describing_tags', [
            'container.service_subscriber',
            'kernel.reset',
        ]);

        $container->register('decorator', DecoratorWithBehavior::class)
            ->setAutoconfigured(true)
            ->setDecoratedService('decorated')
        ;

        $container->registerForAutoconfiguration(ResourceCheckerInterface::class)
            ->addTag('config_cache.resource_checker')
        ;
        $container->registerForAutoconfiguration(ServiceSubscriberInterface::class)
            ->addTag('container.service_subscriber')
        ;
        $container->registerForAutoconfiguration(ResetInterface::class)
            ->addTag('kernel.reset', ['method' => 'reset'])
        ;

        (new ResolveInstanceofConditionalsPass())->process($container);

        self::assertEquals([
            'container.service_subscriber' => [0 => []],
            'kernel.reset' => [
                [
                    'method' => 'reset',
                ],
            ],
        ], $container->getDefinition('decorator')->getTags());
        self::assertFalse($container->hasParameter('container.behavior_describing_tags'));
    }
}

class DecoratorWithBehavior implements ResetInterface, ResourceCheckerInterface, ServiceSubscriberInterface
{
    public function reset()
    {
    }

    public function supports(ResourceInterface $metadata): bool
    {
    }

    public function isFresh(ResourceInterface $resource, $timestamp): bool
    {
    }

    public static function getSubscribedServices(): array
    {
    }
}
