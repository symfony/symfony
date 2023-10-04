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

use PHPUnit\Framework\TestCase;
use Symfony\Component\TypeInfo\Exception\InvalidArgumentException;
use Symfony\Component\TypeInfo\Exception\UnsupportedException;
use Symfony\Component\TypeInfo\Tests\Fixtures\AbstractDummy;
use Symfony\Component\TypeInfo\Tests\Fixtures\Dummy;
use Symfony\Component\TypeInfo\Tests\Fixtures\DummyWithTemplates;
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

    /**
     * @dataProvider resolveDataProvider
     */
    public function testResolve(Type $expectedType, string $string, TypeContext $typeContext = null)
    {
        $this->assertEquals($expectedType, $this->resolver->resolve($string, $typeContext));
    }

    /**
     * @return iterable<array{0: Type, 1: string, 2?: TypeContext}>
     */
    public function resolveDataProvider(): iterable
    {
        $typeContextFactory = new TypeContextFactory(new StringTypeResolver());

        // callable
        yield [Type::callable(), 'callable(string, int): mixed'];

        // array
        yield [Type::array(Type::bool()), 'bool[]'];

        // array shape
        yield [Type::array(), 'array{0: true, 1: false}'];

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
        yield [Type::union(Type::int(), Type::string()), 'array-key'];
        yield [Type::union(Type::int(), Type::float(), Type::string(), Type::bool()), 'scalar'];
        yield [Type::union(Type::int(), Type::float()), 'number'];
        yield [Type::union(Type::int(), Type::float(), Type::string()), 'numeric'];
        yield [Type::object(AbstractDummy::class), 'self', $typeContextFactory->createFromClassName(Dummy::class, AbstractDummy::class)];
        yield [Type::object(Dummy::class), 'static', $typeContextFactory->createFromClassName(Dummy::class, AbstractDummy::class)];
        yield [Type::object(AbstractDummy::class), 'parent', $typeContextFactory->createFromClassName(Dummy::class)];
        yield [Type::object(Dummy::class), 'Dummy', $typeContextFactory->createFromClassName(Dummy::class)];
        yield [Type::template('T', Type::union(Type::int(), Type::string())), 'T', $typeContextFactory->createFromClassName(DummyWithTemplates::class)];
        yield [Type::template('V'), 'V', $typeContextFactory->createFromReflection(new \ReflectionMethod(DummyWithTemplates::class, 'getPrice'))];

        // nullable
        yield [Type::nullable(Type::int()), '?int'];

        // generic
        yield [Type::generic(Type::object(), Type::string(), Type::bool()), 'object<string, bool>'];
        yield [Type::generic(Type::object(), Type::generic(Type::string(), Type::bool())), 'object<string<bool>>'];
        yield [Type::int(), 'int<0, 100>'];

        // union
        yield [Type::union(Type::int(), Type::string()), 'int|string'];

        // intersection
        yield [Type::intersection(Type::int(), Type::string()), 'int&string'];

        // DNF
        yield [Type::union(Type::int(), Type::intersection(Type::string(), Type::bool())), 'int|(string&bool)'];

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

    public function testCannotUnknownIdentifier()
    {
        $this->expectException(UnsupportedException::class);
        $this->resolver->resolve('unknown');
    }
}
