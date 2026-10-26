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
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\IgnoreDeprecations;
use PHPUnit\Framework\TestCase;
use Symfony\Component\TypeInfo\Exception\InvalidArgumentException;
use Symfony\Component\TypeInfo\Exception\LogicException;
use Symfony\Component\TypeInfo\Exception\UnsupportedException;
use Symfony\Component\TypeInfo\Tests\Fixtures\AbstractDummy;
use Symfony\Component\TypeInfo\Tests\Fixtures\Dummy;
use Symfony\Component\TypeInfo\Tests\Fixtures\DummyBackedEnum;
use Symfony\Component\TypeInfo\Tests\Fixtures\DummyBackedEnumInterface;
use Symfony\Component\TypeInfo\Tests\Fixtures\DummyCollection;
use Symfony\Component\TypeInfo\Tests\Fixtures\DummyEnum;
use Symfony\Component\TypeInfo\Tests\Fixtures\DummyWithConstants;
use Symfony\Component\TypeInfo\Tests\Fixtures\DummyWithConstantsInterface;
use Symfony\Component\TypeInfo\Tests\Fixtures\DummyWithTemplates;
use Symfony\Component\TypeInfo\Tests\Fixtures\DummyWithTemplateTypeAlias;
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
        $this->resolver = new StringTypeResolver(null, null, [], true);
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
        $typeContextFactory = new TypeContextFactory(new StringTypeResolver(null, null, [], true));

        /**
         * @template TFoo of int
         * @template TBar of string
         */
        $dummyTemplateKeyUnion = new class {};

        // callable
        yield [Type::callable(), 'callable(string, int): mixed'];

        // array
        yield [Type::array(Type::bool()), 'bool[]'];
        yield [Type::array(Type::bool()), 'array<bool>'];
        yield [Type::array(Type::bool(), Type::int()), 'array<int, bool>'];
        yield [Type::array(Type::bool(), Type::arrayKey()), 'array<array-key, bool>'];
        yield [Type::array(Type::bool(), Type::arrayKey()), 'array<int|string, bool>'];
        yield [Type::array(Type::bool(), Type::arrayKey()), 'non-empty-array<int|string, bool>'];
        yield [Type::array(Type::bool(), Type::template('TKey', Type::union(Type::int(), Type::string()))), 'array<TKey, bool>', $typeContextFactory->createFromClassName(DummyCollection::class)];
        yield [Type::array(Type::template('TValue', Type::mixed()), Type::template('TKey', Type::union(Type::int(), Type::string()))), 'array<TKey, TValue>', $typeContextFactory->createFromClassName(DummyCollection::class)];
        yield [Type::array(Type::template('TValue', Type::mixed()), Type::arrayKey()), 'array<array-key, TValue>', $typeContextFactory->createFromClassName(DummyCollection::class)];
        yield [Type::array(Type::bool(), Type::union(Type::template('TFoo', Type::int()), Type::template('TBar', Type::string()))), 'array<TFoo|TBar, bool>', $typeContextFactory->createFromClassName($dummyTemplateKeyUnion::class)];
        // explicitly test both with fully qualified class name and with imported short class name
        yield [Type::array(Type::bool(), Type::arrayKey()), 'array<\\'.DummyWithConstants::class.'::DUMMY_STRING_A|DummyWithConstants::DUMMY_INT_A, bool>', $typeContextFactory->createFromClassName(DummyWithConstants::class)];

        // list
        yield [Type::list(Type::bool()), 'list<bool>'];
        yield [Type::list(Type::bool()), 'non-empty-list<bool>'];
        yield [Type::list(Type::template('TValue', Type::mixed())), 'list<TValue>', $typeContextFactory->createFromClassName(DummyCollection::class)];

        // array shape
        yield [Type::arrayShape(['foo' => Type::true(), 1 => Type::false()]), 'array{foo: true, 1: false}'];
        yield [Type::arrayShape(['foo' => ['type' => Type::bool(), 'optional' => true]]), 'array{foo?: bool}'];
        yield [Type::arrayShape(['foo' => Type::bool()], sealed: false), 'array{foo: bool, ...}'];
        yield [Type::arrayShape(['foo' => Type::bool()], extraKeyType: Type::int(), extraValueType: Type::string()), 'array{foo: bool, ...<int, string>}'];
        yield [Type::arrayShape(['foo' => Type::bool()], extraValueType: Type::int()), 'array{foo: bool, ...<int>}'];
        yield [Type::arrayShape(['foo' => Type::union(Type::bool(), Type::float(), Type::int(), Type::null(), Type::string()), 'bar' => Type::string()]), 'array{foo: scalar|null, bar: string}'];
        yield [Type::arrayShape(['foo' => Type::int(), 'bar baz' => Type::string()]), "array{'foo': int, \"bar baz\": string}"];
        yield [Type::arrayShape([0 => Type::int()]), "array{'0': int}"];
        yield [Type::arrayShape([Type::int(), Type::string()]), 'array{int, string}'];
        yield [Type::arrayShape(["it's" => Type::int()]), "array{'it\\'s': int}"];

        // object
        yield [Type::object(), 'object'];

        // object shape
        yield [Type::objectShape(['foo' => Type::true(), 'bar' => Type::false()]), 'object{foo: true, bar: false}'];
        yield [Type::objectShape(['foo' => ['type' => Type::bool(), 'optional' => true]]), 'object{foo?: bool}'];
        yield [Type::objectShape(['foo bar' => Type::int()]), "object{'foo bar': int}"];

        // this
        yield [Type::object(Dummy::class), '$this', $typeContextFactory->createFromClassName(Dummy::class, AbstractDummy::class)];

        // const
        yield [Type::array(), 'array[1, 2, 3]'];
        yield [Type::false(), 'false'];
        yield [Type::null(), 'null'];
        yield [Type::true(), 'true'];

        // const fetch
        yield [Type::string(), DummyWithConstants::class.'::DUMMY_STRING_*'];
        yield [Type::string(), DummyWithConstants::class.'::DUMMY_STRING_A'];
        yield [Type::string(), 'DummyWithConstants::DUMMY_STRING_A', $typeContextFactory->createFromClassName(DummyWithConstants::class)];
        yield [Type::string(), 'self::DUMMY_STRING_A', $typeContextFactory->createFromClassName(DummyWithConstants::class)];
        yield [Type::int(), 'static::DUMMY_INT_*', $typeContextFactory->createFromClassName(DummyWithConstants::class)];
        yield [Type::int(), DummyWithConstants::class.'::DUMMY_INT_*'];
        yield [Type::int(), DummyWithConstants::class.'::DUMMY_INT_A'];
        yield [Type::float(), DummyWithConstants::class.'::DUMMY_FLOAT_*'];
        yield [Type::true(), DummyWithConstants::class.'::DUMMY_TRUE_*'];
        yield [Type::false(), DummyWithConstants::class.'::DUMMY_FALSE_*'];
        yield [Type::null(), DummyWithConstants::class.'::DUMMY_NULL_*'];
        yield [Type::array(null, Type::union(Type::int(), Type::string())), DummyWithConstants::class.'::DUMMY_ARRAY_*'];
        yield [Type::enum(DummyEnum::class, Type::string()), DummyWithConstants::class.'::DUMMY_ENUM_*'];
        yield [Type::union(Type::enum(DummyEnum::class, Type::string()), Type::array(Type::mixed(), Type::union(Type::int(), Type::string())), Type::string(), Type::int(), Type::float(), Type::bool(), Type::null()), DummyWithConstants::class.'::DUMMY_MIX_*'];

        // const fetch on interface
        yield [Type::string(), DummyWithConstantsInterface::class.'::DUMMY_STRING_*'];
        yield [Type::int(), DummyWithConstantsInterface::class.'::DUMMY_INT_*'];
        yield [Type::array(Type::string(), Type::string()), 'array<'.DummyWithConstantsInterface::class.'::DUMMY_STRING_*, '.DummyWithConstantsInterface::class.'::DUMMY_STRING_*>'];

        // identifiers
        yield [Type::bool(), 'bool'];
        yield [Type::bool(), 'boolean'];
        yield [Type::true(), 'true'];
        yield [Type::false(), 'false'];
        yield [Type::int(), 'int'];
        yield [Type::int(), 'integer'];
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
        yield [Type::object(DummyBackedEnumInterface::class), 'DummyBackedEnumInterface', $typeContextFactory->createFromClassName(DummyBackedEnumInterface::class)];
        yield [Type::template('T', Type::union(Type::int(), Type::string())), 'T', $typeContextFactory->createFromClassName(DummyWithTemplates::class)];
        yield [Type::template('V'), 'V', $typeContextFactory->createFromReflection(new \ReflectionMethod(DummyWithTemplates::class, 'getPrice'))];

        // nullable
        yield [Type::nullable(Type::int()), '?int'];

        // generic
        yield [Type::generic(Type::object(\DateTime::class), Type::string(), Type::bool()), \DateTime::class.'<string, bool>'];
        yield [Type::generic(Type::object(\DateTime::class), Type::generic(Type::object(\Stringable::class), Type::bool())), \sprintf('%s<%s<bool>>', \DateTime::class, \Stringable::class)];
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

        yield [Type::string(), 'String'];
        yield [Type::int(), 'Int'];
        yield [Type::int(), 'Integer'];
        yield [Type::bool(), 'Bool'];
        yield [Type::bool(), 'Boolean'];
        yield [Type::float(), 'Double'];
        yield [Type::mixed(), 'MIXED'];
        yield [Type::null(), 'NULL'];
        yield [Type::string(), 'Non-Empty-String'];
        yield [Type::array(), 'Array'];
        yield [Type::list(Type::string()), 'List<String>'];
        yield 'mixed-case value-of enum' => [Type::string(), \sprintf('Value-Of<%s>', DummyBackedEnum::class)];
        yield 'mixed-case value-of list' => [Type::bool(), 'Value-Of<list<Bool>>'];
        yield 'mixed-case key-of array' => [Type::string(), 'Key-Of<array<String, Bool>>'];
        yield 'uppercase key-of list' => [Type::int(), 'KEY-OF<LIST<Bool>>'];
        yield 'nested mixed-case generic keywords' => [Type::array(Type::bool(), Type::string()), 'array<Key-Of<array<string, bool>>, VALUE-OF<list<Bool>>>'];
        yield [Type::array(Type::list(Type::int()), Type::string()), 'Array<String, List<Integer>>'];
        yield [Type::arrayShape(['Array' => Type::string(), 'List' => ['type' => Type::int(), 'optional' => true], 'NON-EMPTY-ARRAY' => Type::bool()]), 'array{Array: String, List?: Integer, NON-EMPTY-ARRAY: Bool}'];
        yield [Type::arrayShape(['Array{' => Type::string()]), "array{'Array{': String}"];
        yield [Type::arrayShape(['KEY' => Type::arrayShape(['NESTED' => Type::string()])]), 'array{KEY: array{NESTED: String}}'];
        yield [Type::nullable(Type::int()), '?Int'];
        yield [Type::object(Dummy::class), 'Self', $typeContextFactory->createFromClassName(Dummy::class)];
        yield [Type::object(Dummy::class), 'Number', new TypeContext(Dummy::class, Dummy::class, null, ['Number' => Dummy::class])];
        yield [Type::template('Number', Type::bool()), 'Number', new TypeContext(Dummy::class, Dummy::class, null, [], ['Number' => Type::bool()])];
        yield [Type::string(), 'Number', new TypeContext(Dummy::class, Dummy::class, null, [], [], ['Number' => Type::string()])];

        // type aliases
        yield [Type::int(), 'CustomInt', $typeContextFactory->createFromClassName(DummyWithTypeAliases::class)];
        yield [Type::string(), 'CustomString', $typeContextFactory->createFromClassName(DummyWithTypeAliases::class)];
        yield [Type::template('T'), 'AliasWithTemplate', $typeContextFactory->createFromClassName(DummyWithTemplateTypeAlias::class)];
    }

    public function testResolveWithExtraTypeAlias()
    {
        $resolver = new StringTypeResolver(null, null, ['CustomAlias' => 'int', 'CustomPair' => 'array{0: CustomAlias, 1: CustomAlias}'], true);

        $this->assertEquals(Type::int(), $resolver->resolve('CustomAlias'));
        $this->assertEquals(Type::arrayShape([Type::int(), Type::int()]), $resolver->resolve('CustomPair'));
    }

    public function testDeclaredIdentifierTakesPrecedenceOverKeywordCase()
    {
        $resolver = new StringTypeResolver(null, null, ['Number' => 'string'], true);

        $this->assertEquals(Type::string(), $resolver->resolve('Number'));
        $this->assertEquals(Type::union(Type::int(), Type::float()), $resolver->resolve('number'));
    }

    public function testDeclaredGenericAliasesTakePrecedenceOverKeywordCase()
    {
        $resolver = new StringTypeResolver(null, null, ['Value-Of' => \DateTime::class, 'Key-Of' => \DateTime::class], true);

        foreach (['Value-Of', 'Key-Of'] as $name) {
            $this->assertEquals(Type::generic(Type::object(\DateTime::class), Type::string()), $resolver->resolve($name.'<string>'));
        }
    }

    public function testContextGenericAliasesTakePrecedenceOverKeywordCase()
    {
        $context = new TypeContext(Dummy::class, Dummy::class, null, [], [], ['Value-Of' => Type::object(\DateTime::class), 'Key-Of' => Type::object(\DateTime::class)]);

        foreach (['Value-Of', 'Key-Of'] as $name) {
            $this->assertEquals(Type::generic(Type::object(\DateTime::class), Type::string()), $this->resolver->resolve($name.'<string>', $context));
        }
    }

    #[DataProvider('uppercaseShapeDataProvider')]
    public function testCannotResolveUppercaseShape(string $subject, string $keyword)
    {
        $this->expectException(UnsupportedException::class);

        try {
            $this->resolver->resolve($subject);
        } catch (UnsupportedException $e) {
            $this->assertSame(\sprintf('Use "%s{...}" instead of "%s{...}" for shape types.', strtolower($keyword), $keyword), $e->getPrevious()->getMessage());

            throw $e;
        }
    }

    public static function uppercaseShapeDataProvider(): iterable
    {
        yield ['Array{KEY: String}', 'Array'];
        yield ['Array {KEY: String}', 'Array'];
        yield ["Array\n{KEY: String}", 'Array'];
        yield ["Array\n *  {KEY: String}", 'Array'];
        yield ["List\t{String}", 'List'];
        yield ['ARRAY{KEY?: String, ...<String, Integer>}', 'ARRAY'];
        yield ['List{String, Integer}', 'List'];
        yield ['Non-Empty-Array{KEY: String}', 'Non-Empty-Array'];
        yield ['Non-Empty-List{String}', 'Non-Empty-List'];
        yield ['?Array{KEY: String}', 'Array'];
        yield ['String|Array{KEY: String}', 'Array'];
    }

    public function testUnknownIdentifierIsReportedAsWritten()
    {
        $this->expectException(UnsupportedException::class);

        try {
            $this->resolver->resolve('UnknownClass');
        } catch (UnsupportedException $e) {
            $this->assertSame('Unhandled "UnknownClass" identifier.', $e->getPrevious()->getMessage());

            throw $e;
        }
    }

    public function testExtraTypeAliasNamesAreCaseSensitive()
    {
        $resolver = new StringTypeResolver(null, null, ['custom' => 'string'], true);

        $this->expectException(UnsupportedException::class);
        $resolver->resolve('Custom');
    }

    public function testTypeAliasNamesAreCaseSensitive()
    {
        $context = new TypeContext(Dummy::class, Dummy::class, null, [], [], ['custom' => Type::string()]);

        $this->expectException(UnsupportedException::class);
        $this->resolver->resolve('Custom', $context);
    }

    public function testTemplateNamesAreCaseSensitive()
    {
        $context = new TypeContext(Dummy::class, Dummy::class, null, [], ['t' => Type::string()]);

        $this->expectException(UnsupportedException::class);
        $this->resolver->resolve('T', $context);
    }

    public function testClassNamesArePassedToAutoloadersAsWritten()
    {
        $autoloadedClasses = [];
        $autoload = static function (string $class) use (&$autoloadedClasses): void {
            $autoloadedClasses[] = $class;
        };
        spl_autoload_register($autoload);

        try {
            $this->resolver->resolve('UnknownClassWithOriginalCase');
            $this->fail('An unknown class cannot be resolved.');
        } catch (UnsupportedException) {
            $this->assertContains('UnknownClassWithOriginalCase', $autoloadedClasses);
            $this->assertNotContains('unknownclasswithoriginalcase', $autoloadedClasses);
        } finally {
            spl_autoload_unregister($autoload);
        }
    }

    public function testThrowWhenResolvingRecursiveExtraTypeAlias()
    {
        $resolver = new StringTypeResolver(null, null, ['Foo' => 'list<Foo>'], true);

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('Cannot resolve "Foo" type alias as it is recursive.');

        $resolver->resolve('Foo');
    }

    public function testCannotResolveNonStringType()
    {
        $this->expectException(UnsupportedException::class);
        $this->resolver->resolve(123);
    }

    #[DataProvider('resolveRefinementsDataProvider')]
    public function testResolveRefinements(Type $expectedType, string $string)
    {
        $this->assertEquals($expectedType, $this->resolver->resolve($string));
        $this->assertEquals($expectedType, $this->resolver->resolve((string) $expectedType));
    }

    /**
     * @return iterable<array{0: Type, 1: string}>
     */
    public static function resolveRefinementsDataProvider(): iterable
    {
        yield [Type::literal('draft'), "'draft'"];
        yield [Type::literal(1), '1'];
        yield [Type::literal(-1), '-1'];
        yield [Type::literal(26), '0x1A'];
        yield [Type::literal(15), '0o17'];
        yield [Type::literal(5), '0b101'];
        yield [Type::literal(15), '017'];
        yield [Type::literal(0), '0'];
        yield [Type::literal("it's"), "'it\\'s'"];
        yield [Type::literal("a\n\"b"), '"a\\n\\"b"'];
        if (8 === \PHP_INT_SIZE) {
            yield [Type::literal(\PHP_INT_MAX), '0x7FFFFFFFFFFFFFFF'];
            yield [Type::literal(\PHP_INT_MIN), '-9223372036854775808'];
            yield [Type::literal(\PHP_INT_MIN), '-0x8000000000000000'];
            yield [Type::literal(\PHP_INT_MIN), '-0o1000000000000000000000'];
            yield [Type::literal(\PHP_INT_MIN), '-0b1'.str_repeat('0', 63)];
            yield [Type::intRange(null, 0), 'int<-9223372036854775808, 0>'];
            yield [Type::intRange(0, null), 'int<0, 0x7FFFFFFFFFFFFFFF>'];
        }
        yield [Type::literal(1.5), '1.5'];
        yield [Type::union(Type::literal('draft'), Type::literal('published')), "'draft'|'published'"];
        yield [Type::intRange(1, null), 'positive-int'];
        yield [Type::intRange(null, -1), 'negative-int'];
        yield [Type::intRange(null, 0), 'non-positive-int'];
        yield [Type::intRange(0, null), 'non-negative-int'];
        yield [Type::union(Type::intRange(null, -1), Type::intRange(1, null)), 'non-zero-int'];
        yield [Type::nullable(Type::union(Type::intRange(null, -1), Type::intRange(1, null))), '?non-zero-int'];
        yield [Type::union(Type::intRange(null, -1), Type::intRange(1, null), Type::string()), 'non-zero-int|string'];
        yield [Type::intRange(0, 10), 'int<0, 10>'];
        yield [Type::intRange(null, 5), 'int<min, 5>'];
        yield [Type::intRange(-5, null), 'int<-5, max>'];
        yield [Type::int(), 'int<min, max>'];
    }

    #[DataProvider('resolveIntRangeWithConstantBoundDataProvider')]
    public function testResolveIntRangeWithConstantBound(Type $expectedType, string $string, ?TypeContext $typeContext = null)
    {
        $this->assertEquals($expectedType, $this->resolver->resolve($string, $typeContext));
    }

    /**
     * @return iterable<array{0: Type, 1: string, 2?: TypeContext}>
     */
    public static function resolveIntRangeWithConstantBoundDataProvider(): iterable
    {
        $typeContextFactory = new TypeContextFactory(new StringTypeResolver(null, null, [], true));

        yield [Type::intRange(0, null), 'int<0, PHP_INT_MAX>'];
        yield [Type::intRange(null, 0), 'int<\\PHP_INT_MIN, 0>'];
        yield [Type::int(), 'int<PHP_INT_MIN, PHP_INT_MAX>'];
        yield [Type::intRange(\PHP_INT_MAX, null), 'int<PHP_INT_MAX, max>'];
        yield [Type::intRange(0, null), 'int<0, PHP_INT_MAX>', $typeContextFactory->createFromClassName(DummyWithConstants::class)];
        yield [Type::intRange(0, 1), 'int<0, '.DummyWithConstants::class.'::DUMMY_INT_A>'];
        yield [Type::intRange(1, null), 'int<self::DUMMY_INT_A, max>', $typeContextFactory->createFromClassName(DummyWithConstants::class)];
        yield [Type::int(), 'int<0, UNDEFINED_CONSTANT>'];
    }

    #[DataProvider('invalidRefinementDataProvider')]
    public function testCannotResolveInvalidRefinement(string $string)
    {
        $this->expectException(UnsupportedException::class);

        $this->resolver->resolve($string);
    }

    /**
     * @return iterable<array{0: string}>
     */
    public static function invalidRefinementDataProvider(): iterable
    {
        yield ['int<5, 1>'];
        yield ["int<'a', 5>"];
        yield ['int<max, 5>'];
        yield ['9223372036854775808'];
        yield ['0x8000000000000000'];
        yield ['-9223372036854775809'];
        yield ['-0x8000000000000001'];
        yield ['09'];
        yield ['-0129'];
        yield ['int<09, 10>'];
        yield ['1e400'];
        yield ['-1e400'];
        yield ['int<0, PHP_VERSION>'];
        yield ['int<0, '.DummyWithConstants::class.'::DUMMY_STRING_A>'];
        yield ['int<0, '.DummyWithConstants::class.'::UNDEFINED>'];
        yield ['int<0, UndefinedClass::MAX>'];
    }

    #[Group('legacy')]
    #[IgnoreDeprecations]
    public function testDoNotResolveRefinementsWhenDisabled()
    {
        $resolver = new StringTypeResolver(null, null, [], false);

        $this->assertEquals(Type::string(), $resolver->resolve("'draft'"));
        $this->assertEquals(Type::int(), $resolver->resolve('positive-int'));
        $this->assertEquals(Type::int(), $resolver->resolve('non-zero-int'));
        $this->assertEquals(Type::int(), $resolver->resolve('int<0, 10>'));
    }

    #[Group('legacy')]
    #[IgnoreDeprecations]
    public function testNotPassingTrueForResolveRefinementsIsDeprecated()
    {
        $this->expectUserDeprecationMessage('Since symfony/type-info 8.2: Not passing true for the "$resolveRefinements" argument of "Symfony\Component\TypeInfo\TypeResolver\StringTypeResolver::__construct()" is deprecated; PHPDoc refinements will always be resolved in 9.0.');

        new StringTypeResolver();
    }

    #[Group('legacy')]
    #[IgnoreDeprecations]
    public function testPassingFalseForResolveRefinementsIsDeprecated()
    {
        $this->expectUserDeprecationMessage('Since symfony/type-info 8.2: Not passing true for the "$resolveRefinements" argument of "Symfony\Component\TypeInfo\TypeResolver\StringTypeResolver::__construct()" is deprecated; PHPDoc refinements will always be resolved in 9.0.');

        new StringTypeResolver(null, null, [], false);
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

    #[DataProvider('classKeywordConstantDataProvider')]
    public function testCannotResolveClassKeywordConstantWithoutTypeContext(string $type, string $keyword)
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage(\sprintf('A "%s" must be provided to resolve "%s".', TypeContext::class, $keyword));

        $this->resolver->resolve($type);
    }

    /**
     * @return iterable<array{0: string, 1: string}>
     */
    public static function classKeywordConstantDataProvider(): iterable
    {
        yield ['self::FOO', 'self'];
        yield ['static::FOO_*', 'static'];
        yield ['parent::FOO', 'parent'];
    }

    public function testCannotResolveUnknownIdentifier()
    {
        $this->expectException(UnsupportedException::class);
        $this->resolver->resolve('unknown');
    }

    public function testCannotResolveUnknownConstant()
    {
        $this->expectException(UnsupportedException::class);
        $this->resolver->resolve(DummyWithConstants::class.'::UNKNOWN');
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

    public function testCannotResolveListWithKeyType()
    {
        $this->expectException(UnsupportedException::class);
        $this->resolver->resolve('list<int, string>');
    }

    public function testCannotResolveInvalidNonEmptyListWithKeyType()
    {
        $this->expectException(UnsupportedException::class);
        $this->resolver->resolve('non-empty-list<int, string>');
    }

    public function testCannotResolveInvalidArrayKeyType()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->resolver->resolve('array<mixed, string>');
    }

    public function testCannotResolveInvalidUnionArrayKeyType()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->resolver->resolve('array<int|mixed, string>');
    }

    public function testCannotResolveInvalidTemplateKeyType()
    {
        /**
         * @template TKey of mixed
         */
        $dummyClass = new class {};

        $typeContextFactory = new TypeContextFactory(new StringTypeResolver(null, null, [], true));

        $this->expectException(InvalidArgumentException::class);
        $this->resolver->resolve('array<TKey, string>', $typeContextFactory->createFromClassName($dummyClass::class));
    }

    public function testCannotResolveUnionTemplateKeyTypeWithInvalidBound()
    {
        /**
         * @template TFoo of int
         * @template TBar of float
         */
        $dummyClass = new class {};

        $typeContextFactory = new TypeContextFactory(new StringTypeResolver(null, null, [], true));

        $this->expectException(InvalidArgumentException::class);
        $this->resolver->resolve('array<TFoo|TBar, string>', $typeContextFactory->createFromClassName($dummyClass::class));
    }

    public function testCannotResolveInvalidScalarArrayKeyType()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->resolver->resolve('array<scalar, string>');
    }
}
