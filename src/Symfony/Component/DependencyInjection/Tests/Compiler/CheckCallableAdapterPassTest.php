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
use Symfony\Component\DependencyInjection\Attribute\AutowireCallable;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Dumper\PhpDumper;
use Symfony\Component\DependencyInjection\Exception\RuntimeException;
use Symfony\Component\DependencyInjection\Reference;

class CheckCallableAdapterPassTest extends TestCase
{
    public function testAutowireCallableReportsBothSignaturesDuringCompilation()
    {
        $container = new ContainerBuilder();
        $container->register('target', AdapterTarget::class);
        $container->register('consumer', InvalidAdapterConsumer::class)->setAutowired(true)->setPublic(true);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Cannot create adapter for service "consumer": "Symfony\Component\DependencyInjection\Tests\Compiler\AdapterTarget::withArray(array $value): string" is incompatible with "Symfony\Component\DependencyInjection\Tests\Compiler\StringAdapter::format(string $value): string": parameter #1 has incompatible types.');

        $container->compile();
    }

    #[DataProvider('provideInvalidAdapters')]
    public function testInvalidAdapters(string $interface, string $method, string $reason)
    {
        $container = new ContainerBuilder();
        $container->register('target', AdapterTarget::class);
        $container->register('adapter', $interface)
            ->setFactory(['Closure', 'fromCallable'])
            ->setArguments([[new Reference('target'), $method]])
            ->setLazy(true)
            ->setPublic(true);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage($reason);

        $container->compile();
    }

    public static function provideInvalidAdapters(): iterable
    {
        yield 'argument type' => [StringAdapter::class, 'withArray', 'parameter #1 has incompatible types.'];
        yield 'return type' => [StringAdapter::class, 'returnsArray', 'the return types are incompatible.'];
        yield 'optional interface argument' => [OptionalAdapter::class, 'untyped', 'the target needs more arguments than the adapter guarantees (1 required, 0 guaranteed).'];
        yield 'variadic interface arguments' => [VariadicAdapter::class, 'untyped', 'the target needs more arguments than the adapter guarantees (1 required, 0 guaranteed).'];
        yield 'reference interface default missing arguments' => [ReferenceOptionalAdapter::class, 'withTwoArguments', 'the target needs more arguments than the adapter guarantees (2 required, 1 guaranteed).'];
        yield 'literal false argument' => [StringAdapter::class, 'withArrayOrFalse', 'parameter #1 has incompatible types.'];
        yield 'literal false return' => [ArrayOrFalseAdapter::class, 'returnsInt', 'the return types are incompatible.'];
        yield 'missing arguments' => [StringAdapter::class, 'withTwoArguments', 'the target needs more arguments than the adapter guarantees (2 required, 1 guaranteed).'];
        yield 'private method' => [StringAdapter::class, 'privateMethod', 'the target method must be public.'];
        yield 'missing method' => [StringAdapter::class, 'missingMethod', 'the target method does not exist.'];
        yield 'nullable still disjoint' => [StringAdapter::class, 'withNullableArray', 'parameter #1 has incompatible types.'];
        yield 'union disjoint' => [StringAdapter::class, 'withArrayOrObject', 'parameter #1 has incompatible types.'];
        yield 'intersection disjoint' => [StringAdapter::class, 'withIntersection', 'parameter #1 has incompatible types.'];
        yield 'variadic argument' => [StringAdapter::class, 'withVariadicArray', 'parameter #1 has incompatible types.'];
        yield 'void return' => [StringAdapter::class, 'returnsVoid', 'the return types are incompatible.'];
        yield 'final class' => [ObjectAdapter::class, 'withDateTime', 'parameter #1 has incompatible types.'];
    }

    #[DataProvider('provideCompatibleAdapters')]
    public function testCompatibleAdapters(string $interface, string $method, array $arguments, mixed $expected)
    {
        $container = new ContainerBuilder();
        $container->register('target', AdapterTarget::class);
        $container->register('adapter', $interface)
            ->setFactory(['Closure', 'fromCallable'])
            ->setArguments([[new Reference('target'), $method]])
            ->setLazy(true)
            ->setPublic(true);
        $container->compile();

        $this->assertSame($expected, $container->get('adapter')->format(...$arguments));

        $class = 'CallableAdapterContainer_'.str_replace('\\', '_', $interface).'_'.$method.'_'.md5(serialize($arguments));
        eval('?>'.(new PhpDumper($container))->dump(['class' => $class]));

        $this->assertSame($expected, (new $class())->get('adapter')->format(...$arguments));
    }

    public static function provideCompatibleAdapters(): iterable
    {
        yield 'weak scalar coercion' => [StringAdapter::class, 'withInt', ['123'], '123'];
        yield 'void becomes null' => [NullableReturnAdapter::class, 'returnsVoid', ['foo'], null];
        yield 'void discards return' => [VoidAdapter::class, 'returnsArray', ['foo'], null];
        yield 'untyped return' => [StringAdapter::class, 'untyped', ['foo'], 'foo'];
        yield 'untyped argument' => [StringAdapter::class, 'untypedArgument', ['foo'], 'foo'];
        yield 'union overlap' => [StringAdapter::class, 'withArrayOrString', ['foo'], 'foo'];
        yield 'by reference local copy' => [StringAdapter::class, 'byReference', ['foo'], 'foo!'];
        yield 'nullable overlap' => [NullableAdapter::class, 'withNullableArray', [null], 'null'];
        yield 'Stringable coercion' => [StringableAdapter::class, 'withStringable', [new AdapterStringable()], 'foo'];
        yield 'object Stringable coercion' => [BroadObjectAdapter::class, 'withStringable', [new AdapterStringable()], 'foo'];
        yield 'extra userland arguments' => [StringAdapter::class, 'noArguments', ['foo'], 'foo'];
        yield 'interface names and defaults' => [OptionalAdapter::class, 'withDefault', ['value' => 'foo'], 'foo'];
        yield 'reference interface default' => [ReferenceOptionalAdapter::class, 'untyped', [], 'interface'];
        yield 'target default' => [OptionalAdapter::class, 'withDefault', [], 'target'];
    }

    public function testStaticCallableStringIsCheckedDuringCompilation()
    {
        $container = new ContainerBuilder();
        $container->register('adapter', StringAdapter::class)
            ->setFactory(['Closure', 'fromCallable'])
            ->setArguments([AdapterTarget::class.'::withStaticArray'])
            ->setLazy(true)
            ->setPublic(true);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('AdapterTarget::withStaticArray(array $value): string" is incompatible with "Symfony\Component\DependencyInjection\Tests\Compiler\StringAdapter::format(string $value): string": parameter #1 has incompatible types.');

        $container->compile();
    }

    public function testStaticInterfaceMethodIsRejected()
    {
        $container = new ContainerBuilder();
        $container->register('target', AdapterTarget::class);
        $container->register('adapter', StaticAdapter::class)
            ->setFactory(['Closure', 'fromCallable'])
            ->setArguments([[new Reference('target'), 'untyped']])
            ->setLazy(true)
            ->setPublic(true);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Cannot create adapter for service "adapter" because interface method "Symfony\Component\DependencyInjection\Tests\Compiler\StaticAdapter::format()" is static.');

        $container->compile();
    }

    public function testInternalTargetRejectsExtraArguments()
    {
        $container = new ContainerBuilder();
        $container->register('target', \DateTime::class);
        $container->register('adapter', TwoArgumentAdapter::class)
            ->setFactory(['Closure', 'fromCallable'])
            ->setArguments([[new Reference('target'), 'format']])
            ->setLazy(true)
            ->setPublic(true);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('the interface declares more parameters than the target accepts (2 declared, 1 accepted).');

        $container->compile();
    }

    public function testDynamicTargetIsPreserved()
    {
        $container = new ContainerBuilder();
        $container->register('target', DynamicAdapterTarget::class);
        $container->register('adapter', StringAdapter::class)
            ->setFactory(['Closure', 'fromCallable'])
            ->setArguments([[new Reference('target'), 'dynamicMethod']])
            ->setLazy(true)
            ->setPublic(true);
        $container->compile();

        $this->assertSame('foo', $container->get('adapter')->format('foo'));
    }

    public function testExistingTargetServiceIsPreserved()
    {
        $container = new ContainerBuilder();
        $container->set('target', new AdapterTarget());
        $container->register('adapter', StringAdapter::class)
            ->setFactory(['Closure', 'fromCallable'])
            ->setArguments([[new Reference('target'), 'untyped']])
            ->setLazy(true)
            ->setPublic(true);
        $container->compile();

        $this->assertSame('foo', $container->get('adapter')->format('foo'));
    }

    #[DataProvider('provideDefinitionsChangingTargetClass')]
    public function testChangingTargetClassIsPreserved(?array $factory, array $methodCalls)
    {
        $container = new ContainerBuilder();
        $container->register('target', AdapterTarget::class)->setFactory($factory)->setMethodCalls($methodCalls);
        $container->register('adapter', StringAdapter::class)
            ->setFactory(['Closure', 'fromCallable'])
            ->setArguments([[new Reference('target'), 'withArray']])
            ->setLazy(true)
            ->setPublic(true);
        $container->compile();

        $this->assertSame('foo', $container->get('adapter')->format('foo'));

        $class = 'CallableAdapterChangingClassContainer_'.(int) (bool) $factory;
        eval('?>'.(new PhpDumper($container))->dump(['class' => $class]));

        $this->assertSame('foo', (new $class())->get('adapter')->format('foo'));
    }

    public static function provideDefinitionsChangingTargetClass(): iterable
    {
        yield 'factory' => [[DerivedAdapterTarget::class, 'create'], []];
        yield 'wither' => [null, [['withBroadCallback', [], true]]];
    }

    public function testUnknownFactoryClassIsPreserved()
    {
        $container = new ContainerBuilder();
        $container->register('target', 'stdClass')->setFactory([AdapterTarget::class, 'create']);
        $container->register('adapter', StringAdapter::class)
            ->setFactory(['Closure', 'fromCallable'])
            ->setArguments([[new Reference('target'), 'untyped']])
            ->setLazy(true)
            ->setPublic(true);
        $container->compile();

        $this->assertSame('foo', $container->get('adapter')->format('foo'));
    }
}

interface StaticAdapter
{
    public static function format(string $value): string;
}

interface StringAdapter
{
    public function format(string $value): string;
}

interface OptionalAdapter
{
    public function format(string $value = 'interface'): string;
}

interface NullableAdapter
{
    public function format(?string $value): string;
}

interface VoidAdapter
{
    public function format(string $value): void;
}

interface NullableReturnAdapter
{
    public function format(string $value): ?string;
}

interface StringableAdapter
{
    public function format(\Stringable $value): string;
}

interface BroadObjectAdapter
{
    public function format(object $value): string;
}

interface TwoArgumentAdapter
{
    public function format(string $value, string $other): string;
}

interface ReferenceOptionalAdapter
{
    public function format(string &$value = 'interface'): string;
}

interface VariadicAdapter
{
    public function format(string ...$value): string;
}

interface ArrayOrFalseAdapter
{
    public function format(string $value): array|false;
}

interface ObjectAdapter
{
    public function format(\stdClass $value): string;
}

class InvalidAdapterConsumer
{
    public function __construct(#[AutowireCallable(service: 'target', method: 'withArray')] public StringAdapter $adapter)
    {
    }
}

class AdapterTarget
{
    public static function create()
    {
        return new self();
    }

    public static function withStaticArray(array $value): string
    {
        return 'foo';
    }

    public function withBroadCallback(): self
    {
        return new DerivedAdapterTarget();
    }

    public function withArray(array $value): string
    {
        return 'foo';
    }

    public function returnsArray(string $value): array
    {
        return [];
    }

    public function withArrayOrFalse(array|false $value): string
    {
        return 'foo';
    }

    public function returnsInt(string $value): int
    {
        return 1;
    }

    public function withTwoArguments(string $value, string $other): string
    {
        return $value;
    }

    public function withNullableArray(?array $value): string
    {
        return 'null';
    }

    public function withArrayOrObject(array|\stdClass $value): string
    {
        return 'foo';
    }

    public function withIntersection(\Countable&\Traversable $value): string
    {
        return 'foo';
    }

    public function withVariadicArray(array ...$value): string
    {
        return 'foo';
    }

    public function returnsVoid(string $value): void
    {
    }

    public function withDateTime(\DateTime $value): string
    {
        return 'foo';
    }

    public function withInt(int $value): string
    {
        return (string) $value;
    }

    public function untyped(string $value)
    {
        return $value;
    }

    public function untypedArgument($value): string
    {
        return $value;
    }

    public function withArrayOrString(array|string $value): string
    {
        return $value;
    }

    public function byReference(string &$value): string
    {
        return $value .= '!';
    }

    public function withStringable(string $value): string
    {
        return $value;
    }

    public function noArguments(): string
    {
        return 'foo';
    }

    public function withDefault(string $argument = 'target'): string
    {
        return $argument;
    }

    private function privateMethod(string $value): string
    {
        return $value;
    }
}

class DynamicAdapterTarget
{
    public function __call(string $name, array $arguments): string
    {
        return $arguments[0];
    }
}

class AdapterStringable implements \Stringable
{
    public function __toString(): string
    {
        return 'foo';
    }
}

class DerivedAdapterTarget extends AdapterTarget
{
    public static function create()
    {
        return new self();
    }

    public function withArray(mixed $value): string
    {
        return $value;
    }
}
