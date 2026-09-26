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

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\Alias;
use Symfony\Component\DependencyInjection\Compiler\DecoratorServicePass;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\Exception\InvalidArgumentException;
use Symfony\Component\DependencyInjection\Exception\ServiceNotFoundException;
use Symfony\Component\DependencyInjection\Reference;

class DecoratorServicePassTest extends TestCase
{
    public function testProcessWithoutAlias()
    {
        $container = new ContainerBuilder();
        $fooDefinition = $container
            ->register('foo')
        ;
        $fooExtendedDefinition = $container
            ->register('foo.extended')
            ->setPublic(true)
            ->setDecoratedService('foo')
        ;
        $barDefinition = $container
            ->register('bar')
            ->setPublic(true)
        ;
        $barExtendedDefinition = $container
            ->register('bar.extended')
            ->setPublic(true)
            ->setDecoratedService('bar', 'bar.yoo')
        ;

        $this->process($container);

        $this->assertEquals('foo.extended', $container->getAlias('foo'));
        $this->assertFalse($container->getAlias('foo')->isPublic());

        $this->assertEquals('bar.extended', $container->getAlias('bar'));
        $this->assertTrue($container->getAlias('bar')->isPublic());

        $this->assertSame($fooDefinition, $container->getDefinition('foo.extended.inner'));
        $this->assertFalse($container->getDefinition('foo.extended.inner')->isPublic());

        $this->assertSame($barDefinition, $container->getDefinition('bar.yoo'));
        $this->assertFalse($container->getDefinition('bar.yoo')->isPublic());

        $this->assertNull($fooExtendedDefinition->getDecoratedService());
        $this->assertNull($barExtendedDefinition->getDecoratedService());
    }

    public function testProcessWithAlias()
    {
        $container = new ContainerBuilder();
        $container
            ->register('foo')
            ->setPublic(true)
        ;
        $container->setAlias('foo.alias', new Alias('foo', false));
        $fooExtendedDefinition = $container
            ->register('foo.extended')
            ->setPublic(true)
            ->setDecoratedService('foo.alias')
        ;

        $this->process($container);

        $this->assertEquals('foo.extended', $container->getAlias('foo.alias'));
        $this->assertFalse($container->getAlias('foo.alias')->isPublic());

        $this->assertEquals('foo', $container->getAlias('foo.extended.inner'));
        $this->assertFalse($container->getAlias('foo.extended.inner')->isPublic());

        $this->assertNull($fooExtendedDefinition->getDecoratedService());
    }

    public function testProcessWithPriority()
    {
        $container = new ContainerBuilder();
        $fooDefinition = $container
            ->register('foo')
        ;
        $barDefinition = $container
            ->register('bar')
            ->setPublic(true)
            ->setDecoratedService('foo')
        ;
        $bazDefinition = $container
            ->register('baz')
            ->setPublic(true)
            ->setDecoratedService('foo', null, 5)
        ;
        $quxDefinition = $container
            ->register('qux')
            ->setPublic(true)
            ->setDecoratedService('foo', null, 3)
        ;

        $this->process($container);

        $this->assertEquals('bar', $container->getAlias('foo'));
        $this->assertFalse($container->getAlias('foo')->isPublic());

        $this->assertSame($fooDefinition, $container->getDefinition('baz.inner'));
        $this->assertFalse($container->getDefinition('baz.inner')->isPublic());

        $this->assertEquals('qux', $container->getAlias('bar.inner'));
        $this->assertFalse($container->getAlias('bar.inner')->isPublic());

        $this->assertEquals('baz', $container->getAlias('qux.inner'));
        $this->assertFalse($container->getAlias('qux.inner')->isPublic());

        $this->assertNull($barDefinition->getDecoratedService());
        $this->assertNull($bazDefinition->getDecoratedService());
        $this->assertNull($quxDefinition->getDecoratedService());
    }

    public function testProcessWithInvalidDecorated()
    {
        $container = new ContainerBuilder();
        $decoratorDefinition = $container
            ->register('decorator')
            ->setDecoratedService('unknown_decorated', null, 0, ContainerInterface::IGNORE_ON_INVALID_REFERENCE)
        ;

        $this->process($container);
        $this->assertFalse($container->has('decorator'));

        $container = new ContainerBuilder();
        $decoratorDefinition = $container
            ->register('decorator')
            ->setDecoratedService('unknown_decorated', null, 0, ContainerInterface::NULL_ON_INVALID_REFERENCE)
        ;

        $this->process($container);
        $this->assertTrue($container->has('decorator'));
        $this->assertSame(ContainerInterface::NULL_ON_INVALID_REFERENCE, $decoratorDefinition->decorationOnInvalid);

        $container = new ContainerBuilder();
        $decoratorDefinition = $container
            ->register('decorator')
            ->setDecoratedService('unknown_service')
        ;

        $this->expectException(ServiceNotFoundException::class);
        $this->process($container);
    }

    public function testProcessNoInnerAliasWithInvalidDecorated()
    {
        $container = new ContainerBuilder();
        $container
            ->register('decorator')
            ->setDecoratedService('unknown_decorated', null, 0, ContainerInterface::NULL_ON_INVALID_REFERENCE)
        ;

        $this->process($container);
        $this->assertFalse($container->hasAlias('decorator.inner'));
    }

    public function testProcessWithInvalidDecoratedAndWrongBehavior()
    {
        $container = new ContainerBuilder();
        $container
            ->register('decorator')
            ->setDecoratedService('unknown_decorated', null, 0, 12)
        ;

        $this->expectException(ServiceNotFoundException::class);
        $this->process($container);
    }

    public function testProcessMovesTagsFromDecoratedDefinitionToDecoratingDefinition()
    {
        $container = new ContainerBuilder();
        $container
            ->register('foo')
            ->setTags(['bar' => ['attr' => 'baz']])
        ;
        $container
            ->register('baz')
            ->setTags(['foobar' => ['attr' => 'bar']])
            ->setDecoratedService('foo')
        ;

        $this->process($container);

        $this->assertSame([], $container->getDefinition('baz.inner')->getTags());
        $this->assertEquals(['bar' => ['attr' => 'baz'], 'foobar' => ['attr' => 'bar'], 'container.decorator' => [['id' => 'foo', 'inner' => 'baz.inner']]], $container->getDefinition('baz')->getTags());
    }

    public function testProcessMovesTagsFromDecoratedDefinitionToDecoratingDefinitionMultipleTimes()
    {
        $container = new ContainerBuilder();
        $container
            ->register('foo')
            ->setPublic(true)
            ->setTags(['bar' => ['attr' => 'baz']])
        ;
        $container
            ->register('deco1')
            ->setDecoratedService('foo', null, 50)
        ;
        $container
            ->register('deco2')
            ->setDecoratedService('foo', null, 2)
        ;

        $this->process($container);

        $this->assertSame([], $container->getDefinition('deco1')->getTags());
        $this->assertEquals(['bar' => ['attr' => 'baz'], 'container.decorator' => [['id' => 'foo', 'inner' => 'deco1.inner']]], $container->getDefinition('deco2')->getTags());
    }

    #[DataProvider('provideDecoratorsOfDecorators')]
    public function testProcessMovesTagsToTheOutermostDecoratorOfTheService(array $decorators, string $expectedOutermostDecorator)
    {
        $container = new ContainerBuilder();
        $container
            ->register('foo')
            ->setTags(['bar' => ['attr' => 'baz']])
        ;
        foreach ($decorators as $id => [$decoratedId, $priority]) {
            $container->register($id)->setDecoratedService($decoratedId, null, $priority);
        }

        $this->process($container);

        $this->assertSame([$expectedOutermostDecorator], array_keys($container->findTaggedServiceIds('bar')));
    }

    public static function provideDecoratorsOfDecorators(): iterable
    {
        yield 'decorator of a decorator processed first' => [['deco1' => ['foo', 0], 'deco2' => ['deco1', 5]], 'deco2'];
        yield 'decorator of a decorator processed in between' => [['deco1' => ['foo', 5], 'deco2' => ['deco1', 3], 'deco3' => ['foo', 0]], 'deco3'];
        yield 'decorator of an inner decorator' => [['deco1' => ['foo', 0], 'deco2' => ['foo', -1], 'deco3' => ['deco1', 5]], 'deco2'];
    }

    public function testProcessKeepsTheOrderOfTheDecoratorsOfADecoratorOfAMissingService()
    {
        $container = new ContainerBuilder();
        $container
            ->register('deco1')
            ->setDecoratedService('foo', null, 0, ContainerInterface::IGNORE_ON_INVALID_REFERENCE)
        ;
        $container
            ->register('deco2')
            ->setDecoratedService('deco1', null, 5)
        ;

        $this->process($container);

        $this->assertSame('deco2', (string) $container->getAlias('deco1'));
    }

    public function testProcessLeavesServiceLocatorTagOnOriginalDefinition()
    {
        $container = new ContainerBuilder();
        $container
            ->register('foo')
            ->setTags(['container.service_locator' => [0 => []], 'bar' => ['attr' => 'baz']])
        ;
        $container
            ->register('baz')
            ->setTags(['foobar' => ['attr' => 'bar']])
            ->setDecoratedService('foo')
        ;

        $this->process($container);

        $this->assertEquals(['container.service_locator' => [0 => []]], $container->getDefinition('baz.inner')->getTags());
        $this->assertEquals(['bar' => ['attr' => 'baz'], 'foobar' => ['attr' => 'bar'], 'container.decorator' => [['id' => 'foo', 'inner' => 'baz.inner']]], $container->getDefinition('baz')->getTags());
    }

    public function testProcessLeavesServiceSubscriberTagOnOriginalDefinition()
    {
        $container = new ContainerBuilder();
        $container
            ->register('foo')
            ->setTags(['container.service_subscriber' => [], 'container.service_subscriber.locator' => [], 'bar' => ['attr' => 'baz']])
        ;
        $container
            ->register('baz')
            ->setTags(['foobar' => ['attr' => 'bar']])
            ->setDecoratedService('foo')
        ;

        $this->process($container);

        $this->assertEquals(['container.service_subscriber' => [], 'container.service_subscriber.locator' => []], $container->getDefinition('baz.inner')->getTags());
        $this->assertEquals(['bar' => ['attr' => 'baz'], 'foobar' => ['attr' => 'bar'], 'container.decorator' => [['id' => 'foo', 'inner' => 'baz.inner']]], $container->getDefinition('baz')->getTags());
    }

    public function testProcessLeavesProxyTagOnOriginalDefinition()
    {
        $container = new ContainerBuilder();
        $container
            ->register('foo')
            ->setTags(['proxy' => 'foo', 'bar' => ['attr' => 'baz']])
        ;
        $container
            ->register('baz')
            ->setTags(['foobar' => ['attr' => 'bar']])
            ->setDecoratedService('foo')
        ;

        $this->process($container);

        $this->assertEquals(['proxy' => 'foo'], $container->getDefinition('baz.inner')->getTags());
        $this->assertEquals(['bar' => ['attr' => 'baz'], 'foobar' => ['attr' => 'bar'], 'container.decorator' => [['id' => 'foo', 'inner' => 'baz.inner']]], $container->getDefinition('baz')->getTags());
    }

    public function testProcessMovesRoleDescribingTagsWithoutBehaviorDescribingTagsParameter()
    {
        $container = new ContainerBuilder();
        $container
            ->register('foo')
            ->setTags(['container.service_locator' => [0 => []], 'kernel.event_listener' => [['event' => 'foo']]])
        ;
        $container
            ->register('baz')
            ->setDecoratedService('foo')
        ;

        $this->process($container);

        $this->assertEquals(['container.service_locator' => [0 => []]], $container->getDefinition('baz.inner')->getTags());
        $this->assertEquals(['kernel.event_listener' => [['event' => 'foo']], 'container.decorator' => [['id' => 'foo', 'inner' => 'baz.inner']]], $container->getDefinition('baz')->getTags());
    }

    public function testProcessLeavesBehaviorDescribingTagsFromParameterOnOriginalDefinition()
    {
        $container = new ContainerBuilder();
        $container->setParameter('container.behavior_describing_tags', ['container.service_locator', 'logger_aware']);
        $container
            ->register('foo')
            ->setTags(['container.service_locator' => [0 => []], 'logger_aware' => [[]], 'kernel.event_listener' => [['event' => 'foo']]])
        ;
        $container
            ->register('baz')
            ->setDecoratedService('foo')
        ;

        $this->process($container);

        $this->assertEquals(['container.service_locator' => [0 => []], 'logger_aware' => [[]]], $container->getDefinition('baz.inner')->getTags());
        $this->assertEquals(['kernel.event_listener' => [['event' => 'foo']], 'container.decorator' => [['id' => 'foo', 'inner' => 'baz.inner']]], $container->getDefinition('baz')->getTags());
    }

    public function testCannotDecorateSyntheticService()
    {
        $container = new ContainerBuilder();
        $container
            ->register('foo')
            ->setSynthetic(true)
        ;
        $container
            ->register('baz')
            ->setDecoratedService('foo')
        ;

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('A synthetic service cannot be decorated: service "baz" cannot decorate "foo".');
        $this->process($container);
    }

    public function testGenericInnerReference()
    {
        $container = new ContainerBuilder();
        $container->register('foo');

        $container->register('bar')
            ->setDecoratedService('foo')
            ->setProperty('prop', new Reference('.inner'));

        $this->process($container);

        $this->assertEquals(['prop' => new Reference('bar.inner')], $container->getDefinition('bar')->getProperties());
    }

    public function testWithinReordersDecoratorsSharingAPriority()
    {
        $container = new ContainerBuilder();
        $container->register('foo');
        $container->register('a')->setDecoratedService('foo');
        $container->register('b')->setDecoratedService('foo')->addTag('container.decoration_order', ['within' => ['a'], 'priority' => 0]);

        $this->process($container);

        $this->assertSame(['a', 'b'], $this->getDecorationChain($container, 'foo'));
    }

    public function testAroundReordersDecoratorsSharingAPriority()
    {
        $container = new ContainerBuilder();
        $container->register('foo');
        $container->register('a')->setDecoratedService('foo')->addTag('container.decoration_order', ['around' => 'c', 'priority' => 0]);
        $container->register('b')->setDecoratedService('foo');
        $container->register('c')->setDecoratedService('foo');

        $this->process($container);

        $this->assertSame(['b', 'a', 'c'], $this->getDecorationChain($container, 'foo'));
    }

    public function testADecoratorWithoutPriorityIsPlacedByItsConstraints()
    {
        $container = new ContainerBuilder();
        $container->register('http_client');
        $container->register('retryable')->setDecoratedService('http_client', null, 10);
        $container->register('traceable')->setDecoratedService('http_client', null, 5);
        $container->register('uri_template')->setDecoratedService('http_client')->addTag('container.decoration_order', ['around' => ['retryable'], 'within' => ['traceable'], 'priority' => null]);

        $this->process($container);

        $this->assertSame(['traceable', 'uri_template', 'retryable'], $this->getDecorationChain($container, 'http_client'));
        $this->assertSame(5, $container->getDefinition('uri_template')->decorationPriority);
    }

    public function testAPriorityDeclaredInTheTagIsAClaim()
    {
        $container = new ContainerBuilder();
        $container->register('foo');
        $container->register('a')->setDecoratedService('foo', null, 10);
        $container->register('b')->setDecoratedService('foo', null, 5)->addTag('container.decoration_order', ['within' => ['a'], 'priority' => 5]);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid "within"/"around" constraints on the decorators of "foo": the priority of "b" (5) contradicts its "within" constraint on "a" (10): raise it to 10 or more, remove it, or drop the constraint.');

        $this->process($container);
    }

    public function testTheDecorationPriorityIsAClaimWhenTheTagDeclaresNone()
    {
        $container = new ContainerBuilder();
        $container->register('foo');
        $container->register('a')->setDecoratedService('foo', null, 10);
        $container->register('b')->setDecoratedService('foo', null, 7)->addTag('container.decoration_order', ['within' => ['a']]);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid "within"/"around" constraints on the decorators of "foo": the priority of "b" (7) contradicts its "within" constraint on "a" (10): raise it to 10 or more, remove it, or drop the constraint.');

        $this->process($container);
    }

    public function testTheDecorationPriorityOfADecoratorWithoutConstraintsIsAClaim()
    {
        $container = new ContainerBuilder();
        $container->register('foo');
        $container->register('a')->setDecoratedService('foo');
        $container->register('b')->setDecoratedService('foo', null, 10)->addTag('container.decoration_order', ['around' => ['a'], 'priority' => 10]);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid "within"/"around" constraints on the decorators of "foo": the priority of "b" (10) contradicts its "around" constraint on "a" (0): lower it to 0 or less, remove it, or drop the constraint.');

        $this->process($container);
    }

    public function testCyclesAreReported()
    {
        $container = new ContainerBuilder();
        $container->register('foo');
        $container->register('a')->setDecoratedService('foo')->addTag('container.decoration_order', ['within' => ['b']]);
        $container->register('b')->setDecoratedService('foo')->addTag('container.decoration_order', ['within' => ['a']]);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid "within"/"around" constraints on the decorators of "foo": cycle detected in the "within"/"around" constraints: "a" -> "b" -> "a".');

        $this->process($container);
    }

    public function testTargetsThatDoNotDecorateTheSameServiceAreIgnored()
    {
        $container = new ContainerBuilder();
        $container->register('foo');
        $container->register('bar');
        $container->register('a')->setDecoratedService('foo');
        $container->register('b')->setDecoratedService('foo')->addTag('container.decoration_order', ['within' => ['c', 'missing']]);
        $container->register('c')->setDecoratedService('bar');

        $this->process($container);

        $this->assertSame(['b', 'a'], $this->getDecorationChain($container, 'foo'));
        $this->assertSame(['c'], $this->getDecorationChain($container, 'bar'));
    }

    public function testDecoratorsCanBeTargetedByClass()
    {
        $container = new ContainerBuilder();
        $container->setParameter('a.class', 'App\\A');
        $container->register('foo');
        $container->register('a', '%a.class%')->setDecoratedService('foo');
        $container->register('b', 'App\\B')->setDecoratedService('foo')->addTag('container.decoration_order', ['within' => ['App\\A']]);

        $this->process($container);

        $this->assertSame(['a', 'b'], $this->getDecorationChain($container, 'foo'));
    }

    public function testDecoratorsCanBeTargetedByTheirAlias()
    {
        $container = new ContainerBuilder();
        $container->register('foo');
        $container->register('generated_logging')->setDecoratedService('foo')->addTag('container.decoration_order', ['around' => ['caching']]);
        $container->register('generated_caching')->setDecoratedService('foo')->addTag('container.decoration_order', ['alias' => 'caching']);

        $this->process($container);

        $this->assertSame(['generated_logging', 'generated_caching'], $this->getDecorationChain($container, 'foo'));
    }

    public function testADecoratedDecoratorCanBeReordered()
    {
        $container = new ContainerBuilder();
        $fooDefinition = $container->register('foo');
        $container->register('a')->setDecoratedService('foo');
        $container->register('c')->setDecoratedService('a');
        $container->register('b')->setDecoratedService('foo')->addTag('container.decoration_order', ['within' => ['a']]);

        $this->process($container);

        $this->assertSame('a', (string) $container->getAlias('foo'));
        $this->assertSame('c', (string) $container->getAlias('a'));
        $this->assertSame('b', (string) $container->getAlias('a.inner'));
        $this->assertSame($fooDefinition, $container->getDefinition('b.inner'));
    }

    public function testTheOrderTagIsRemoved()
    {
        $container = new ContainerBuilder();
        $container->register('foo');
        $container->register('a')->setDecoratedService('foo')->addTag('container.decoration_order', ['within' => ['b']]);
        $container->register('b')->addTag('container.decoration_order', ['within' => ['a']]);

        $this->process($container);

        $this->assertFalse($container->getDefinition('a')->hasTag('container.decoration_order'));
        $this->assertFalse($container->getDefinition('b')->hasTag('container.decoration_order'));
    }

    protected function process(ContainerBuilder $container)
    {
        $pass = new DecoratorServicePass();
        $pass->process($container);
    }

    private function getDecorationChain(ContainerBuilder $container, string $id): array
    {
        $chain = [];

        while ($container->hasAlias($id)) {
            $chain[] = $id = (string) $container->getAlias($id);
            $id .= '.inner';
        }

        return $chain;
    }
}
