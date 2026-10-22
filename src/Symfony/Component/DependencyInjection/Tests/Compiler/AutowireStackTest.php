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
use Symfony\Component\DependencyInjection\Attribute\AutowireInline;
use Symfony\Component\DependencyInjection\Attribute\AutowireStack;
use Symfony\Component\DependencyInjection\ChildDefinition;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Dumper\PhpDumper;
use Symfony\Component\DependencyInjection\Exception\LogicException;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Contracts\Service\Attribute\Required;

class AutowireStackTest extends TestCase
{
    public function testOrderAndConfiguredArguments()
    {
        $container = $this->createContainer();
        $container->compile();

        $service = $container->get('consumer')->service;

        $this->assertSame('outer(inner(leaf))', $service->label());
        $this->assertInstanceOf(StackOuter::class, $service);
        $this->assertInstanceOf(StackDecorator::class, $service->inner);
        $this->assertInstanceOf(StackLeaf::class, $service->inner->inner);
    }

    public function testConsumersHaveSeparateStacksAndKeepTheGlobalAlias()
    {
        $container = $this->createContainer();
        $container->register('other_consumer', StackConsumer::class)
            ->setAutowired(true)
            ->setPublic(true);
        $container->register('global', StackLeaf::class)
            ->setArguments(['global'])
            ->setPublic(true);
        $container->setAlias(StackService::class, 'global')->setPublic(true);
        $container->compile();

        $first = $container->get('consumer')->service;
        $second = $container->get('other_consumer')->service;

        $this->assertSame('outer(inner(leaf))', $first->label());
        $this->assertSame($first->label(), $second->label());
        $this->assertNotSame($first, $second);
        $this->assertNotSame($first->inner, $second->inner);
        $this->assertNotSame($first->inner->inner, $second->inner->inner);
        $this->assertSame($container->get('global'), $container->get(StackService::class));
        $this->assertSame('global', $container->get(StackService::class)->label());
    }

    public function testDumpedContainer()
    {
        $container = $this->createContainer();
        $container->compile();

        eval('?>'.(new PhpDumper($container))->dump(['class' => 'Symfony_DI_AutowireStack_Test_Container']));

        $container = new \Symfony_DI_AutowireStack_Test_Container();

        $this->assertSame('outer(inner(leaf))', $container->get('consumer')->service->label());
    }

    public function testConstructorAndRequiredSetter()
    {
        $container = $this->createContainer(StackRequiredConsumer::class);
        $container->compile();

        $consumer = $container->get('consumer');

        $this->assertSame('outer(inner(leaf))', $consumer->service->label());
        $this->assertSame('outer(leaf)', $consumer->setterService->label());
        $this->assertNotSame($consumer->service, $consumer->setterService);
        $this->assertSame(1, $consumer->setterCalls);
    }

    public function testConfiguredRequiredSetterWithParameter()
    {
        $container = $this->createContainer(StackRequiredConsumer::class);
        $container->setParameter('setter', 'setService');
        $container->getDefinition('consumer')->addMethodCall('%setter%');
        $container->compile();

        $consumer = $container->get('consumer');

        $this->assertSame(1, $consumer->setterCalls);
        $this->assertSame('outer(leaf)', $consumer->setterService->label());
    }

    public function testInheritedConsumer()
    {
        $container = new ContainerBuilder();
        $container->register('consumer_template', StackConsumer::class)
            ->setAbstract(true)
            ->setAutowired(true);
        $container->setDefinition('consumer', new ChildDefinition('consumer_template'))
            ->setPublic(true);
        $container->compile();

        $this->assertSame('outer(inner(leaf))', $container->get('consumer')->service->label());
    }

    public function testParentFrame()
    {
        $container = $this->createContainer(StackParentConsumer::class);
        $container->register('stack.decorator_template', StackDecorator::class)
            ->setAbstract(true)
            ->setAutowired(true)
            ->setArgument('$name', 'template');
        $container->compile();

        $this->assertSame('parent(leaf)', $container->get('consumer')->service->label());
    }

    public function testChildInlineAttributeKeepsPrecedence()
    {
        $container = new ContainerBuilder();
        $container->register('template', StackSingleInlineConsumer::class)
            ->setAbstract(true)
            ->setArgument('$service', new Reference('override'));
        $container->register('override', StackLeaf::class)
            ->setArguments(['override']);
        $container->setDefinition('consumer', (new ChildDefinition('template'))
            ->setClass(StackSingleInlineConsumer::class)
            ->setAutowired(true)
            ->setPublic(true));
        $container->compile();

        $this->assertSame('leaf', $container->get('consumer')->service->label());
    }

    public function testStackAttributeOnFrame()
    {
        $container = $this->createContainer(StackNestedConsumer::class);
        $container->compile();

        $this->assertSame('nested[inner(leaf)](leaf)', $container->get('consumer')->service->label());
    }

    public function testStackAttributeInsideInlineDefinition()
    {
        $container = $this->createContainer(StackInlineConsumer::class);
        $container->compile();

        $this->assertSame('nested[inner(leaf)](leaf)', $container->get('consumer')->nested->service->label());
    }

    public function testConfiguredFrameWithStackArgument()
    {
        $container = $this->createContainer(StackConfiguredNestedConsumer::class);
        $container->compile();

        $this->assertSame('nested[outer(leaf)](leaf)', $container->get('consumer')->service->label());
    }

    public function testExplicitArgumentOverridesAttribute()
    {
        $container = $this->createContainer();
        $container->register('override', StackLeaf::class)
            ->setArguments(['override'])
            ->setPublic(true);
        $container->getDefinition('consumer')->setArgument('$service', new Reference('override'));
        $container->compile();

        $this->assertSame($container->get('override'), $container->get('consumer')->service);
        $this->assertSame('override', $container->get('consumer')->service->label());
    }

    public function testSingleFrame()
    {
        $container = $this->createContainer(StackSingleConsumer::class);
        $container->compile();

        $this->assertSame('leaf', $container->get('consumer')->service->label());
    }

    #[DataProvider('provideConsumerTypes')]
    public function testConsumerTypes(string $class)
    {
        $container = $this->createContainer($class);
        $container->compile();

        $this->assertSame('outer(leaf)', $container->get('consumer')->service->label());
    }

    public static function provideConsumerTypes(): iterable
    {
        yield 'nullable' => [StackNullableConsumer::class];
        yield 'union' => [StackUnionConsumer::class];
        yield 'untyped' => [StackUntypedConsumer::class];
    }

    public function testParametersInFrames()
    {
        $container = $this->createContainer(StackParameterConsumer::class);
        $container->setParameter('stack.outer_class', StackOuter::class);
        $container->setParameter('stack.decorator_class', StackDecorator::class);
        $container->setParameter('stack.leaf_class', StackLeaf::class);
        $container->setParameter('stack.label', 'configured');
        $container->compile();

        $this->assertSame('outer(configured(leaf))', $container->get('consumer')->service->label());
    }

    public function testFactoryFrame()
    {
        $container = $this->createContainer(StackFactoryConsumer::class);
        $container->compile();

        $this->assertSame('outer(factory)', $container->get('consumer')->service->label());
    }

    public function testStackAttributeInConfiguredStack()
    {
        $container = new ContainerBuilder();
        $container->setDefinition('stack', (new ChildDefinition(''))
            ->addTag('container.stack')
            ->setArguments([
                (new Definition(StackNestedDecorator::class))->setAutowired(true),
                (new Definition(StackLeaf::class))->setAutowired(true),
            ])
            ->setPublic(true));
        $container->compile();

        $this->assertSame('nested[inner(leaf)](leaf)', $container->get('stack')->label());
    }

    #[DataProvider('provideRecursiveFrames')]
    public function testRecursiveStack(string $class)
    {
        $container = $this->createContainer($class);

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('Circular reference detected while expanding #[AutowireStack]');

        $container->compile();
    }

    public static function provideRecursiveFrames(): iterable
    {
        yield 'self' => [StackRecursiveFrame::class];
        yield 'mutual' => [StackMutualFirst::class];
    }

    public function testRepeatedParameterWithDifferentStack()
    {
        $container = $this->createContainer(StackRecursiveFrame::class);
        $container->getDefinition('consumer')->setArgument('$inner', new AutowireStack([
            new AutowireInline(StackRecursiveFrame::class, [
                '$inner' => new AutowireStack([StackLeaf::class]),
            ]),
        ]));
        $container->compile();

        $this->assertSame('outer(outer(leaf))', $container->get('consumer')->label());
    }

    private function createContainer(string $class = StackConsumer::class): ContainerBuilder
    {
        $container = new ContainerBuilder();
        $container->register('consumer', $class)
            ->setAutowired(true)
            ->setPublic(true);

        return $container;
    }
}

interface StackService
{
    public function label(): string;
}

class StackLeaf implements StackService
{
    public function __construct(private string $name = 'leaf')
    {
    }

    public function label(): string
    {
        return $this->name;
    }
}

class StackOuter implements StackService
{
    public function __construct(public StackService $inner)
    {
    }

    public function label(): string
    {
        return 'outer('.$this->inner->label().')';
    }
}

class StackDecorator implements StackService
{
    public function __construct(public StackService $inner, private string $name)
    {
    }

    public function label(): string
    {
        return $this->name.'('.$this->inner->label().')';
    }
}

class StackConsumer
{
    public function __construct(
        #[AutowireStack([
            StackOuter::class,
            new AutowireInline(StackDecorator::class, ['$name' => 'inner']),
            StackLeaf::class,
        ])]
        public StackService $service,
    ) {
    }
}

class StackRequiredConsumer extends StackConsumer
{
    public StackService $setterService;
    public int $setterCalls = 0;

    #[Required]
    public function setService(
        #[AutowireStack([StackOuter::class, StackLeaf::class])]
        StackService $service,
    ): void {
        $this->setterService = $service;
        ++$this->setterCalls;
    }
}

class StackParentConsumer
{
    public function __construct(
        #[AutowireStack([
            new AutowireInline(arguments: ['$name' => 'parent'], parent: 'stack.decorator_template'),
            StackLeaf::class,
        ])]
        public StackService $service,
    ) {
    }
}

class StackNestedDecorator implements StackService
{
    public function __construct(
        #[AutowireStack([
            new AutowireInline(StackDecorator::class, ['$name' => 'inner']),
            StackLeaf::class,
        ])]
        public StackService $dependency,
        public StackService $inner,
    ) {
    }

    public function label(): string
    {
        return 'nested['.$this->dependency->label().']('.$this->inner->label().')';
    }
}

class StackNestedConsumer
{
    public function __construct(
        #[AutowireStack([StackNestedDecorator::class, StackLeaf::class])]
        public StackService $service,
    ) {
    }
}

class StackInlineConsumer
{
    public function __construct(
        #[AutowireInline(StackNestedConsumer::class)]
        public StackNestedConsumer $nested,
    ) {
    }
}

class StackConfiguredNestedConsumer
{
    public function __construct(
        #[AutowireStack([
            new AutowireInline(StackNestedDecorator::class, [
                '$dependency' => new AutowireStack([StackOuter::class, StackLeaf::class]),
            ]),
            StackLeaf::class,
        ])]
        public StackService $service,
    ) {
    }
}

class StackSingleConsumer
{
    public function __construct(
        #[AutowireStack([StackLeaf::class])]
        public StackService $service,
    ) {
    }
}

class StackSingleInlineConsumer
{
    public function __construct(
        #[AutowireInline(StackLeaf::class)]
        public StackService $service,
    ) {
    }
}

class StackNullableConsumer
{
    public function __construct(
        #[AutowireStack([StackOuter::class, StackLeaf::class])]
        public ?StackService $service,
    ) {
    }
}

class StackUnionConsumer
{
    public function __construct(
        #[AutowireStack([StackOuter::class, StackLeaf::class])]
        public StackService|\stdClass $service,
    ) {
    }
}

class StackUntypedConsumer
{
    public function __construct(
        #[AutowireStack([StackOuter::class, StackLeaf::class])]
        public $service,
    ) {
    }
}

class StackParameterConsumer
{
    public function __construct(
        #[AutowireStack([
            '%stack.outer_class%',
            new AutowireInline('%stack.decorator_class%', ['$name' => '%stack.label%']),
            '%stack.leaf_class%',
        ])]
        public StackService $service,
    ) {
    }
}

class StackFactoryConsumer
{
    public function __construct(
        #[AutowireStack([
            StackOuter::class,
            new AutowireInline([StackFactory::class, 'create'], ['factory']),
        ])]
        public StackService $service,
    ) {
    }
}

class StackFactory
{
    public static function create(string $name): StackService
    {
        return new StackLeaf($name);
    }
}

class StackRecursiveFrame extends StackOuter
{
    public function __construct(
        #[AutowireStack([self::class])]
        StackService $inner,
    ) {
        parent::__construct($inner);
    }
}

class StackMutualFirst extends StackOuter
{
    public function __construct(
        #[AutowireStack([StackMutualSecond::class])]
        StackService $inner,
    ) {
        parent::__construct($inner);
    }
}

class StackMutualSecond extends StackOuter
{
    public function __construct(
        #[AutowireStack([StackMutualFirst::class])]
        StackService $inner,
    ) {
        parent::__construct($inner);
    }
}
