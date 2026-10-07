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

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\RequiresPhp;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\Compiler\PassConfig;
use Symfony\Component\DependencyInjection\Compiler\RegisterAsCallableAttributesPass;
use Symfony\Component\DependencyInjection\Compiler\RegisterCallableServicesPass;
use Symfony\Component\DependencyInjection\Compiler\ResolveInstanceofConditionalsPass;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Exception\InvalidArgumentException;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\DependencyInjection\Tests\Fixtures\RegisterCallableServicesPass\AbstractRule;
use Symfony\Component\DependencyInjection\Tests\Fixtures\RegisterCallableServicesPass\BadAttributes;
use Symfony\Component\DependencyInjection\Tests\Fixtures\RegisterCallableServicesPass\BarRule;
use Symfony\Component\DependencyInjection\Tests\Fixtures\RegisterCallableServicesPass\CallableRule;
use Symfony\Component\DependencyInjection\Tests\Fixtures\RegisterCallableServicesPass\CallableRuleInterface;
use Symfony\Component\DependencyInjection\Tests\Fixtures\RegisterCallableServicesPass\ClosureTagInterface;
use Symfony\Component\DependencyInjection\Tests\Fixtures\RegisterCallableServicesPass\ClosureTagRule;
use Symfony\Component\DependencyInjection\Tests\Fixtures\RegisterCallableServicesPass\ConcreteRule;
use Symfony\Component\DependencyInjection\Tests\Fixtures\RegisterCallableServicesPass\DuplicateTarget;
use Symfony\Component\DependencyInjection\Tests\Fixtures\RegisterCallableServicesPass\ExporterInterface;
use Symfony\Component\DependencyInjection\Tests\Fixtures\RegisterCallableServicesPass\Exporters;
use Symfony\Component\DependencyInjection\Tests\Fixtures\RegisterCallableServicesPass\FixedIdChild;
use Symfony\Component\DependencyInjection\Tests\Fixtures\RegisterCallableServicesPass\FixedIdMethod;
use Symfony\Component\DependencyInjection\Tests\Fixtures\RegisterCallableServicesPass\FooRule;
use Symfony\Component\DependencyInjection\Tests\Fixtures\RegisterCallableServicesPass\IdOnInterface;
use Symfony\Component\DependencyInjection\Tests\Fixtures\RegisterCallableServicesPass\InheritsMethod;
use Symfony\Component\DependencyInjection\Tests\Fixtures\RegisterCallableServicesPass\KeyedTags;
use Symfony\Component\DependencyInjection\Tests\Fixtures\RegisterCallableServicesPass\NonPublicMethod;
use Symfony\Component\DependencyInjection\Tests\Fixtures\RegisterCallableServicesPass\NotAnInterfaceAdapter;
use Symfony\Component\DependencyInjection\Tests\Fixtures\RegisterCallableServicesPass\OtherCallableRule;
use Symfony\Component\DependencyInjection\Tests\Fixtures\RegisterCallableServicesPass\OtherClosureTagRule;
use Symfony\Component\DependencyInjection\Tests\Fixtures\RegisterCallableServicesPass\OverridesMethod;
use Symfony\Component\DependencyInjection\Tests\Fixtures\RegisterCallableServicesPass\RuleInterface;
use Symfony\Component\DependencyInjection\Tests\Fixtures\RegisterCallableServicesPass\Rules;
use Symfony\Component\DependencyInjection\Tests\Fixtures\RegisterCallableServicesPass\SubRule;
use Symfony\Component\DependencyInjection\Tests\Fixtures\RegisterCallableServicesPass\SubRuleInterface;
use Symfony\Component\DependencyInjection\Tests\Fixtures\RegisterCallableServicesPass\TargetOnInterface;
use Symfony\Component\DependencyInjection\Tests\Fixtures\RegisterCallableServicesPass\TooManyMethodsAdapter;

#[CoversClass(RegisterAsCallableAttributesPass::class)]
#[CoversClass(RegisterCallableServicesPass::class)]
class RegisterCallableServicesPassTest extends TestCase
{
    public function testRegistersAClosureServicePerAttributedMethod()
    {
        $container = new ContainerBuilder();
        $container->register('app.rules', Rules::class)->setAutoconfigured(true);

        $this->process($container);

        $definition = $container->getDefinition('app.rules::isAdult');

        $this->assertSame('Closure', $definition->getClass());
        $this->assertSame(['Closure', 'fromCallable'], $definition->getFactory());
        $this->assertEquals([[new Reference('app.rules'), 'isAdult']], $definition->getArguments());
        $this->assertTrue($definition->isLazy());
        $this->assertFalse($definition->isPublic());
        $this->assertSame([[]], $definition->getTag('app.rule'));

        $this->assertFalse($container->has('app.rules::notExposed'));
    }

    public function testStaticMethodsDontReferenceTheDeclaringService()
    {
        $container = new ContainerBuilder();
        $container->register('app.rules', Rules::class)->setAutoconfigured(true);

        $this->process($container);

        $definition = $container->getDefinition('app.rule.is_even');

        $this->assertSame([[Rules::class, 'isEven']], $definition->getArguments());
        $this->assertFalse($definition->isLazy());
        $this->assertSame([['priority' => 10]], $definition->getTag('app.rule'));
    }

    public function testEachServiceOfTheSameClassGetsItsOwnClosureServices()
    {
        $container = new ContainerBuilder();
        $this->registerInterface($container, RuleInterface::class);
        $container->register('app.foo_rule', FooRule::class)->setAutoconfigured(true);
        $container->register('app.spare_rule', FooRule::class)->setAutoconfigured(true);

        $this->process($container);

        $this->assertEquals([[new Reference('app.foo_rule'), 'evaluate']], $container->getDefinition('app.foo_rule::evaluate')->getArguments());
        $this->assertEquals([[new Reference('app.spare_rule'), 'evaluate']], $container->getDefinition('app.spare_rule::evaluate')->getArguments());
    }

    public function testTheDeclaringServiceIsInstantiatedOnTheFirstCall()
    {
        $container = new ContainerBuilder();
        $container->register('app.rules', Rules::class)->setAutoconfigured(true);
        $container->addCompilerPass(new class implements CompilerPassInterface {
            public function process(ContainerBuilder $container): void
            {
                $container->getDefinition('app.rules::isAdult')->setPublic(true);
            }
        }, PassConfig::TYPE_BEFORE_OPTIMIZATION);

        $container->compile();

        try {
            $isAdult = $container->get('app.rules::isAdult');

            $this->assertInstanceOf(\Closure::class, $isAdult);
            $this->assertSame(0, Rules::$instantiations);

            $this->assertTrue($isAdult(20));
            $this->assertFalse($isAdult(10));
            $this->assertSame(1, Rules::$instantiations);
        } finally {
            Rules::$instantiations = 0;
        }
    }

    public function testNothingIsRegisteredWithoutAutoconfiguration()
    {
        $container = new ContainerBuilder();
        $container->register('app.rules', Rules::class);

        $this->process($container);

        $this->assertFalse($container->has('app.rules::isAdult'));
    }

    public function testNothingIsRegisteredWhenAttributesAreIgnored()
    {
        $container = new ContainerBuilder();
        $container->register('app.rules', Rules::class)
            ->setAutoconfigured(true)
            ->addTag('container.ignore_attributes');

        $this->process($container);

        $this->assertFalse($container->has('app.rules::isAdult'));
    }

    public function testAbstractServicesGetNoClosureService()
    {
        $container = new ContainerBuilder();
        $this->registerInterface($container, RuleInterface::class);
        $container->register('app.abstract_rule', FooRule::class)
            ->setAbstract(true)
            ->setAutoconfigured(true);

        $this->process($container);

        $this->assertFalse($container->has('app.abstract_rule::evaluate'));
    }

    public function testAnInheritedMethodIsStillAttributed()
    {
        $container = new ContainerBuilder();
        $container->register('app.inherits', InheritsMethod::class)->setAutoconfigured(true);

        $this->process($container);

        $definition = $container->getDefinition('app.inherits::evaluate');

        $this->assertEquals([[new Reference('app.inherits'), 'evaluate']], $definition->getArguments());
        $this->assertSame([[]], $definition->getTag('app.rule'));
    }

    public function testOverridingTheMethodDropsTheAttribute()
    {
        $container = new ContainerBuilder();
        $container->register('app.overrides', OverridesMethod::class)->setAutoconfigured(true);

        $this->process($container);

        $this->assertFalse($container->has('app.overrides::evaluate'));
    }

    public function testAttributeIsInheritedFromTheInterface()
    {
        $container = new ContainerBuilder();
        $this->registerInterface($container, RuleInterface::class);
        $container->register('app.foo_rule', FooRule::class)->setAutoconfigured(true);

        $this->process($container);

        $definition = $container->getDefinition('app.foo_rule::evaluate');

        $this->assertEquals([[new Reference('app.foo_rule'), 'evaluate']], $definition->getArguments());
        $this->assertSame([[]], $definition->getTag('app.rule'));
    }

    public function testTheInterfaceItselfGetsNoClosureService()
    {
        $container = new ContainerBuilder();
        $this->registerInterface($container, RuleInterface::class);

        $this->process($container);

        $this->assertFalse($container->has(RuleInterface::class.'::evaluate'));
    }

    public function testInterfacesWithoutADefinitionAreIgnored()
    {
        $container = new ContainerBuilder();
        $container->register('app.foo_rule', FooRule::class)->setAutoconfigured(true);

        $this->process($container);

        $this->assertFalse($container->has('app.foo_rule::evaluate'));
    }

    public function testAttributeOnTheClassWinsOverTheInterface()
    {
        $container = new ContainerBuilder();
        $this->registerInterface($container, RuleInterface::class);
        $container->register('app.bar_rule', BarRule::class)->setAutoconfigured(true);

        $this->process($container);

        $definition = $container->getDefinition('app.bar_rule::evaluate');

        $this->assertSame([], $definition->getTag('app.rule'));
        $this->assertSame([[]], $definition->getTag('app.other_rule'));
    }

    #[DataProvider('provideInterfaceOrders')]
    public function testTheSubInterfaceWinsOverTheOneItExtends(array $interfaces)
    {
        $container = new ContainerBuilder();
        foreach ($interfaces as $interface) {
            $this->registerInterface($container, $interface);
        }
        $container->register('app.sub_rule', SubRule::class)->setAutoconfigured(true);

        $this->process($container);

        $definition = $container->getDefinition('app.sub_rule::evaluate');

        $this->assertSame([], $definition->getTag('app.rule'));
        $this->assertSame([[]], $definition->getTag('app.sub_rule'));
    }

    public static function provideInterfaceOrders(): iterable
    {
        yield 'extended interface first' => [[RuleInterface::class, SubRuleInterface::class]];
        yield 'sub-interface first' => [[SubRuleInterface::class, RuleInterface::class]];
    }

    public function testAbstractMethodsReachTheClassesImplementingThem()
    {
        $container = new ContainerBuilder();
        $this->registerInterface($container, AbstractRule::class);
        $container->register('app.concrete_rule', ConcreteRule::class)->setAutoconfigured(true);

        $this->process($container);

        $definition = $container->getDefinition('app.concrete_rule::evaluate');

        $this->assertEquals([[new Reference('app.concrete_rule'), 'evaluate']], $definition->getArguments());
        $this->assertSame([['key' => 'concrete']], $definition->getTag('app.rule'));
    }

    public function testTagDeclaredWithANameKey()
    {
        $container = new ContainerBuilder();
        $container->register('app.rules', Rules::class)->setAutoconfigured(true);

        $this->process($container);

        $this->assertSame([['priority' => -5]], $container->getDefinition('app.rules::isMinor')->getTag('app.rule'));
    }

    public function testTagAttributesComputedByACallable()
    {
        $container = new ContainerBuilder();
        $this->registerInterface($container, CallableRuleInterface::class);
        $container->register('app.callable_rule', CallableRule::class)->setAutoconfigured(true);
        $container->register('app.other_callable_rule', OtherCallableRule::class)->setAutoconfigured(true);

        $this->process($container);

        $this->assertSame([['key' => 'callable']], $container->getDefinition('app.callable_rule::evaluate')->getTag('app.rule'));
        $this->assertSame([['key' => 'other callable']], $container->getDefinition('app.other_callable_rule::evaluate')->getTag('app.rule'));
    }

    #[RequiresPhp('>=8.5.0')]
    public function testTagAttributesComputedByAClosure()
    {
        $container = new ContainerBuilder();
        $this->registerInterface($container, ClosureTagInterface::class);
        $container->register('app.closure_rule', ClosureTagRule::class)->setAutoconfigured(true);
        $container->register('app.other_closure_rule', OtherClosureTagRule::class)->setAutoconfigured(true);

        $this->process($container);

        $this->assertSame([['key' => 'closure']], $container->getDefinition('app.closure_rule::evaluate')->getTag('app.rule'));
        $this->assertSame([['key' => 'other closure']], $container->getDefinition('app.other_closure_rule::evaluate')->getTag('app.rule'));
    }

    public function testTheRelayTagIsRemovedFromEveryService()
    {
        $container = new ContainerBuilder();
        $this->registerInterface($container, RuleInterface::class);
        $container->register('app.rules', Rules::class)->setAutoconfigured(true);
        $container->register('app.foo_rule', FooRule::class)->setAutoconfigured(true);

        $this->process($container);

        foreach ($container->getDefinitions() as $id => $definition) {
            if ($definition->hasTag('container.excluded')) {
                continue;
            }

            $this->assertFalse($definition->hasTag(RegisterCallableServicesPass::TAG), \sprintf('Service "%s" still carries the relay tag.', $id));
        }
    }

    public function testAClosureServiceCanImplementAnInterface()
    {
        $container = new ContainerBuilder();
        $container->register('app.exporters', Exporters::class)->setAutoconfigured(true);

        $this->process($container);

        $definition = $container->getDefinition('app.exporters::exportCsv');
        $this->assertSame(ExporterInterface::class, $definition->getClass());
        $this->assertEquals([[new Reference('app.exporters'), 'exportCsv']], $definition->getArguments());
        $this->assertTrue($definition->isLazy());

        $definition = $container->getDefinition('app.exporters::exportTsv');
        $this->assertSame(ExporterInterface::class, $definition->getClass());
        $this->assertSame([[Exporters::class, 'exportTsv']], $definition->getArguments());
        $this->assertTrue($definition->isLazy());
    }

    public function testTargetRegistersANamedAutowiringAlias()
    {
        $container = new ContainerBuilder();
        $container->register('app.exporters', Exporters::class)->setAutoconfigured(true);

        $this->process($container);

        $this->assertSame('app.exporters::exportCsv', (string) $container->getAlias(ExporterInterface::class.' $csv'));
        $this->assertSame('app.exporters::exportHtml', (string) $container->getAlias('Closure $htmlExporter'));
        $this->assertSame('Closure $htmlExporter', (string) $container->getAlias('.Closure $html exporter'));
    }

    public function testTagsKeyedByNameAreRejected()
    {
        $container = new ContainerBuilder();
        $container->register('app.keyed', KeyedTags::class)->setAutoconfigured(true);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('must be a list of tags, as everywhere else tags are declared, not a map keyed by tag name.');

        $this->process($container);
    }

    public function testNonScalarTagAttributesAreRejected()
    {
        $container = new ContainerBuilder();
        $container->register('app.bad', BadAttributes::class)->setAutoconfigured(true);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('The "handler" attribute of the "app.rule" tag declared by "#[AsCallable]" on "Symfony\Component\DependencyInjection\Tests\Fixtures\RegisterCallableServicesPass\BadAttributes::evaluate()" must be of a scalar type, "stdClass" given.');

        $this->process($container);
    }

    public function testNonPublicMethodsAreRejected()
    {
        $container = new ContainerBuilder();
        $container->register('app.hidden', NonPublicMethod::class)->setAutoconfigured(true);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('The "#[AsCallable]" attribute cannot be used on the non-public method "Symfony\Component\DependencyInjection\Tests\Fixtures\RegisterCallableServicesPass\NonPublicMethod::hidden()".');

        $this->process($container);
    }

    public function testCollidingIdsAreRejected()
    {
        $container = new ContainerBuilder();
        $container->register('app.rules', Rules::class)->setAutoconfigured(true);
        $container->register('app.rule.is_even', \stdClass::class);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Cannot register the service "app.rule.is_even"');

        $this->process($container);
    }

    public function testIdAndTargetAreRejectedOnInheritedMethods()
    {
        $container = new ContainerBuilder();
        $container->register('app.child', FixedIdChild::class)->setAutoconfigured(true);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage(\sprintf('The "id" and "target" options of "#[AsCallable]" cannot be used on the inherited method "%s::evaluate()": every service inheriting it would claim them.', FixedIdMethod::class));

        $this->process($container);
    }

    #[DataProvider('provideInvalidAdapters')]
    public function testLazyMustBeASingleMethodInterface(string $class, string $method, string $given)
    {
        $container = new ContainerBuilder();
        $container->register('app.adapter', $class)->setAutoconfigured(true);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage(\sprintf('The "lazy" option of "#[AsCallable]" on "%s::%s()" must be a boolean or an interface that has exactly one method, "%s" given.', $class, $method, $given));

        $this->process($container);
    }

    public static function provideInvalidAdapters(): iterable
    {
        yield 'not an interface' => [NotAnInterfaceAdapter::class, 'count', \ArrayObject::class];
        yield 'several methods' => [TooManyMethodsAdapter::class, 'current', \Iterator::class];
    }

    public function testDuplicateTargetsAreRejected()
    {
        $container = new ContainerBuilder();
        $container->register('app.exporters', Exporters::class)->setAutoconfigured(true);
        $container->register('app.duplicate', DuplicateTarget::class)->setAutoconfigured(true);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage(\sprintf('Cannot bind the target "csv" declared by "#[AsCallable]" on "%s::export()" because the "%s $csv" alias already exists.', DuplicateTarget::class, ExporterInterface::class));

        $this->process($container);
    }

    #[DataProvider('provideNamedInterfaces')]
    public function testIdAndTargetAreRejectedOnInterfaceMethods(string $interface)
    {
        $container = new ContainerBuilder();
        $this->registerInterface($container, $interface);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage(\sprintf('The "id" and "target" options of "#[AsCallable]" cannot be used on the abstract method "%s::evaluate()": every service implementing it would claim them.', $interface));

        $this->process($container);
    }

    public static function provideNamedInterfaces(): iterable
    {
        yield 'id' => [IdOnInterface::class];
        yield 'target' => [TargetOnInterface::class];
    }

    private function process(ContainerBuilder $container): void
    {
        (new RegisterAsCallableAttributesPass())->process($container);
        (new ResolveInstanceofConditionalsPass())->process($container);
        (new RegisterCallableServicesPass())->process($container);
    }

    /**
     * Mirrors what FileLoader::registerClasses() registers for a discovered interface.
     */
    private function registerInterface(ContainerBuilder $container, string $interface): void
    {
        $container->register($interface, $interface)
            ->setAbstract(true)
            ->setAutoconfigured(true)
            ->addTag('container.excluded', ['source' => 'because the class is abstract']);
    }
}
