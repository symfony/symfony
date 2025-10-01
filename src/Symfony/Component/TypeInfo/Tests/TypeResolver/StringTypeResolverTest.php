<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\TypeInfo\Tests\TypeResolver;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\TypeInfo\Exception\InvalidArgumentException;
use Symfony\Component\TypeInfo\Exception\UnsupportedException;
use Symfony\Component\TypeInfo\Tests\Fixtures\AbstractDummy;
use Symfony\Component\TypeInfo\Tests\Fixtures\Dummy;
use Symfony\Component\TypeInfo\Tests\Fixtures\DummyBackedEnum;
use Symfony\Component\TypeInfo\Tests\Fixtures\DummyCollection;
use Symfony\Component\TypeInfo\Tests\Fixtures\DummyEnum;
use Symfony\Component\TypeInfo\Tests\Fixtures\DummyWithConstants;
use Symfony\Component\TypeInfo\Tests\Fixtures\DummyWithTemplates;
use Symfony\Component\TypeInfo\Tests\Fixtures\DummyWithTypeAliases;
use Symfony\Component\TypeInfo\Type;
use Symfony\Component\TypeInfo\TypeContext\TypeContext;
use Symfony\Component\TypeInfo\TypeContext\TypeContextFactory;
use Symfony\Component\TypeInfo\TypeResolver\StringTypeResolver;

class StringTypeResolverTest extends TestCase
{
    private StringTypeResolver $resolver;

    protected function setUp(): void
    {
        $this->resolver = new StringTypeResolver();
    }

    #[DataProvider('resolveDataProvider')]
    public function testResolve(Type $expectedType, string $string, ?TypeContext $typeContext = null)
    {
        $this->assertEquals($expectedType, $this->resolver->resolve($string, $typeContext));
    }

    #[DataProvider('resolveDataProvider')]
    public function testResolveStringable(Type $expectedType, string $string, ?TypeContext $typeContext = null)
    {
        $this->assertEquals($expectedType, $this->resolver->resolve(new class($string) implements \Stringable {
            public function __construct(private string $value)
            {
            }

            public function __toString(): string
            {
                return $this->value;
            }
        }, $typeContext));
    }

    /**
     * @return iterable<array{0: Type, 1: string, 2?: TypeContext}>
     */
    public static function resolveDataProvider(): iterable
    {
        $typeContextFactory = new TypeContextFactory(new StringTypeResolver());

        // callable
        yield [Type::callable(), 'callable(string, int): mixed'];

        // array
        yield [Type::list(Type::bool()), 'bool[]'];

        // array shape
        yield [Type::arrayShape(['foo' => Type::true(), 1 => Type::false()]), 'array{foo: true, 1: false}'];
        yield [Type::arrayShape(['foo' => ['type' => Type::bool(), 'optional' => true]]), 'array{foo?: bool}'];
        yield [Type::arrayShape(['foo' => Type::bool()], sealed: false), 'array{foo: bool, ...}'];
        yield [Type::arrayShape(['foo' => Type::bool()], extraKeyType: Type::int(), extraValueType: Type::string()), 'array{foo: bool, ...<int, string>}'];
        yield [Type::arrayShape(['foo' => Type::bool()], extraValueType: Type::int()), 'array{foo: bool, ...<int>}'];
        yield [Type::arrayShape(['foo' => Type::union(Type::bool(), Type::float(), Type::int(), Type::null(), Type::string()), 'bar' => Type::string()]), 'array{foo: scalar|null, bar: string}'];

        // object shape
        yield [Type::object(), 'object{foo: true, bar: false}'];

        // this
        yield [Type::object(Dummy::class), '$this', $typeContextFactory->createFromClassName(Dummy::class, AbstractDummy::class)];

        // const
        yield [Type::array(), 'array[1, 2, 3]'];
        yield [Type::false(), 'false'];
        yield [Type::float(), '1.23'];
        yield [Type::int(), '1'];
        yield [Type::null(), 'null'];
        yield [Type::string(), '"string"'];
        yield [Type::true(), 'true'];

        // const fetch
        yield [Type::string(), DummyWithConstants::class.'::DUMMY_STRING_*'];
        yield [Type::string(), DummyWithConstants::class.'::DUMMY_STRING_A'];
        yield [Type::int(), DummyWithConstants::class.'::DUMMY_INT_*'];
        yield [Type::int(), DummyWithConstants::class.'::DUMMY_INT_A'];
        yield [Type::float(), DummyWithConstants::class.'::DUMMY_FLOAT_*'];
        yield [Type::true(), DummyWithConstants::class.'::DUMMY_TRUE_*'];
        yield [Type::false(), DummyWithConstants::class.'::DUMMY_FALSE_*'];
        yield [Type::null(), DummyWithConstants::class.'::DUMMY_NULL_*'];
        yield [Type::array(null, Type::union(Type::int(), Type::string())), DummyWithConstants::class.'::DUMMY_ARRAY_*'];
        yield [Type::enum(DummyEnum::class, Type::string()), DummyWithConstants::class.'::DUMMY_ENUM_*'];
        yield [Type::union(Type::enum(DummyEnum::class, Type::string()), Type::array(Type::mixed(), Type::union(Type::int(), Type::string())), Type::string(), Type::int(), Type::float(), Type::bool(), Type::null()), DummyWithConstants::class.'::DUMMY_MIX_*'];

        // identifiers
        yield [Type::bool(), 'bool'];
        yield [Type::bool(), 'boolean'];
        yield [Type::true(), 'true'];
        yield [Type::false(), 'false'];
        yield [Type::int(), 'int'];
        yield [Type::int(), 'integer'];
        yield [Type::int(), 'positive-int'];
        yield [Type::int(), 'negative-int'];
        yield [Type::int(), 'non-positive-int'];
        yield [Type::int(), 'non-negative-int'];
        yield [Type::int(), 'non-zero-int'];
        yield [Type::float(), 'float'];
        yield [Type::float(), 'double'];
        yield [Type::string(), 'string'];
        yield [Type::string(), 'class-string'];
        yield [Type::string(), 'trait-string'];
        yield [Type::string(), 'interface-string'];
        yield [Type::string(), 'callable-string'];
        yield [Type::string(), 'numeric-string'];
        yield [Type::string(), 'lowercase-string'];
        yield [Type::string(), 'non-empty-lowercase-string'];
        yield [Type::string(), 'non-empty-string'];
        yield [Type::string(), 'non-falsy-string'];
        yield [Type::string(), 'truthy-string'];
        yield [Type::string(), 'literal-string'];
        yield [Type::string(), 'html-escaped-string'];
        yield [Type::resource(), 'resource'];
        yield [Type::object(), 'object'];
        yield [Type::callable(), 'callable'];
        yield [Type::array(), 'array'];
        yield [Type::array(), 'non-empty-array'];
        yield [Type::list(), 'list'];
        yield [Type::list(), 'non-empty-list'];
        yield [Type::iterable(), 'iterable'];
        yield [Type::mixed(), 'mixed'];
        yield [Type::null(), 'null'];
        yield [Type::void(), 'void'];
        yield [Type::never(), 'never'];
        yield [Type::never(), 'never-return'];
        yield [Type::never(), 'never-returns'];
        yield [Type::never(), 'no-return'];
        yield [Type::arrayKey(), 'array-key'];
        yield [Type::union(Type::int(), Type::float(), Type::string(), Type::bool()), 'scalar'];
        yield [Type::union(Type::int(), Type::float()), 'number'];
        yield [Type::union(Type::int(), Type::float(), Type::string()), 'numeric'];
        yield [Type::object(AbstractDummy::class), 'self', $typeContextFactory->createFromClassName(Dummy::class, AbstractDummy::class)];
        yield [Type::object(Dummy::class), 'static', $typeContextFactory->createFromClassName(Dummy::class, AbstractDummy::class)];
        yield [Type::object(AbstractDummy::class), 'parent', $typeContextFactory->createFromClassName(Dummy::class)];
        yield [Type::object(Dummy::class), 'Dummy', $typeContextFactory->createFromClassName(Dummy::class)];
        yield [Type::enum(DummyEnum::class), 'DummyEnum', $typeContextFactory->createFromClassName(DummyEnum::class)];
        yield [Type::enum(DummyBackedEnum::class), 'DummyBackedEnum', $typeContextFactory->createFromClassName(DummyBackedEnum::class)];
        yield [Type::template('T', Type::union(Type::int(), Type::string())), 'T', $typeContextFactory->createFromClassName(DummyWithTemplates::class)];
        yield [Type::template('V'), 'V', $typeContextFactory->createFromReflection(new \ReflectionMethod(DummyWithTemplates::class, 'getPrice'))];

        // nullable
        yield [Type::nullable(Type::int()), '?int'];

        // generic
        yield [Type::generic(Type::object(\DateTime::class), Type::string(), Type::bool()), \DateTime::class.'<string, bool>'];
        yield [Type::generic(Type::object(\DateTime::class), Type::generic(Type::object(\Stringable::class), Type::bool())), \sprintf('%s<%s<bool>>', \DateTime::class, \Stringable::class)];
        yield [Type::int(), 'int<0, 100>'];
        yield [Type::string(), \sprintf('value-of<%s>', DummyBackedEnum::class)];
        yield [Type::int(), 'key-of<list<bool>>'];
        yield [Type::bool(), 'value-of<list<bool>>'];

        // union
        yield [Type::union(Type::int(), Type::string()), 'int|string'];
        yield [Type::mixed(), 'int|mixed'];
        yield [Type::mixed(), 'mixed|int'];

        // intersection
        yield [Type::intersection(Type::object(\DateTime::class), Type::object(\Stringable::class)), \DateTime::class.'&'.\Stringable::class];

        // DNF
        yield [Type::union(Type::int(), Type::intersection(Type::object(\DateTime::class), Type::object(\Stringable::class))), \sprintf('int|(%s&%s)', \DateTime::class, \Stringable::class)];

        // collection objects
        yield [Type::collection(Type::object(\Traversable::class)), \Traversable::class];
        yield [Type::collection(Type::object(\Traversable::class), Type::string()), \Traversable::class.'<string>'];
        yield [Type::collection(Type::object(\Traversable::class), Type::bool(), Type::string()), \Traversable::class.'<string, bool>'];
        yield [Type::collection(Type::object(\Iterator::class)), \Iterator::class];
        yield [Type::collection(Type::object(\Iterator::class), Type::string()), \Iterator::class.'<string>'];
        yield [Type::collection(Type::object(\Iterator::class), Type::bool(), Type::string()), \Iterator::class.'<string, bool>'];
        yield [Type::collection(Type::object(\IteratorAggregate::class)), \IteratorAggregate::class];
        yield [Type::collection(Type::object(\IteratorAggregate::class), Type::string()), \IteratorAggregate::class.'<string>'];
        yield [Type::collection(Type::object(\IteratorAggregate::class), Type::bool(), Type::string()), \IteratorAggregate::class.'<string, bool>'];
        yield [Type::collection(Type::object(DummyCollection::class), Type::bool(), Type::string()), DummyCollection::class.'<string, bool>'];

        // type aliases
        yield [Type::int(), 'CustomInt', $typeContextFactory->createFromClassName(DummyWithTypeAliases::class)];
        yield [Type::string(), 'CustomString', $typeContextFactory->createFromClassName(DummyWithTypeAliases::class)];
    }

    public function testResolveWithExtraTypeAlias()
    {
        $resolver = new StringTypeResolver(null, null, ['CustomAlias' => 'int']);

        $this->assertEquals(Type::int(), $resolver->resolve('CustomAlias'));
    }

    public function testCannotResolveNonStringType()
    {
        $this->expectException(UnsupportedException::class);
        $this->resolver->resolve(123);
    }

    public function testCannotResolveThisWithoutTypeContext()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->resolver->resolve('$this');
    }

    public function testCannotResolveSelfWithoutTypeContext()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->resolver->resolve('self');
    }

    public function testCannotResolveStaticWithoutTypeContext()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->resolver->resolve('static');
    }

    public function testCannotResolveParentWithoutTypeContext()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->resolver->resolve('parent');
    }

    public function testCannotResolveUnknownIdentifier()
    {
        $this->expectException(UnsupportedException::class);
        $this->resolver->resolve('unknown');
    }

    public function testCannotResolveKeyOfInvalidType()
    {
        $this->expectException(UnsupportedException::class);
        $this->resolver->resolve('key-of<int>');
    }

    public function testCannotResolveValueOfInvalidType()
    {
        $this->expectException(UnsupportedException::class);
        $this->resolver->resolve('value-of<int>');
    }
}
