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

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\IgnoreDeprecations;
use PHPUnit\Framework\TestCase;
use Symfony\Component\TypeInfo\Exception\UnsupportedException;
use Symfony\Component\TypeInfo\Refinement;
use Symfony\Component\TypeInfo\Tests\Fixtures\Dummy;
use Symfony\Component\TypeInfo\Type;
use Symfony\Component\TypeInfo\TypeResolver\TypeResolver;
use Symfony\Component\TypeInfo\TypeResolver\TypeResolverInterface;

class TypeResolverTest extends TestCase
{
    public function testResolve()
    {
        $resolver = TypeResolver::create(null, true);

        $this->assertEquals(Type::bool(), $resolver->resolve('bool'));
        $this->assertEquals(Type::intRange(1, null), $resolver->resolve('positive-int'));
        $this->assertEquals(Type::int(), $resolver->resolve((new \ReflectionProperty(Dummy::class, 'id'))->getType()));
        $this->assertEquals(Type::int(), $resolver->resolve((new \ReflectionMethod(Dummy::class, 'setId'))->getParameters()[0]));
        $this->assertEquals(Type::int(), $resolver->resolve(new \ReflectionProperty(Dummy::class, 'id')));
        $this->assertEquals(Type::void(), $resolver->resolve(new \ReflectionMethod(Dummy::class, 'setId')));
        $this->assertEquals(Type::string(), $resolver->resolve(new \ReflectionFunction(strtoupper(...))));
    }

    #[Group('legacy')]
    #[IgnoreDeprecations]
    public function testNotPassingRefinementsIsDeprecated()
    {
        $this->expectUserDeprecationMessage('Since symfony/type-info 8.2: Not passing the "$refinements" argument of "Symfony\Component\TypeInfo\TypeResolver\TypeResolver::create()" is deprecated; all PHPDoc refinements will be resolved by default in 9.0. Pass true to resolve them now, or false to keep their builtin types.');

        TypeResolver::create();
    }

    public function testCreateWithoutRefinements()
    {
        $this->assertEquals(Type::int(), TypeResolver::create(null, false)->resolve('positive-int'));
        $this->assertEquals(Type::union(Type::literal('a'), Type::literal('b')), TypeResolver::create(null, [Refinement::Literal])->resolve("'a'|'b'"));
    }

    public function testCannotFindResolver()
    {
        $this->expectException(UnsupportedException::class);
        $this->expectExceptionMessage('Cannot find any resolver for "int" type.');

        $resolver = TypeResolver::create([]);
        $resolver->resolve(1);
    }

    public function testUseProperResolver()
    {
        $stringResolver = $this->createStub(TypeResolverInterface::class);
        $stringResolver->method('resolve')->willReturn(Type::template('STRING'));

        $reflectionTypeResolver = $this->createStub(TypeResolverInterface::class);
        $reflectionTypeResolver->method('resolve')->willReturn(Type::template('REFLECTION_TYPE'));

        $reflectionParameterResolver = $this->createStub(TypeResolverInterface::class);
        $reflectionParameterResolver->method('resolve')->willReturn(Type::template('REFLECTION_PARAMETER'));

        $reflectionPropertyResolver = $this->createStub(TypeResolverInterface::class);
        $reflectionPropertyResolver->method('resolve')->willReturn(Type::template('REFLECTION_PROPERTY'));

        $reflectionReturnTypeResolver = $this->createStub(TypeResolverInterface::class);
        $reflectionReturnTypeResolver->method('resolve')->willReturn(Type::template('REFLECTION_RETURN_TYPE'));

        $resolver = TypeResolver::create([
            'string' => $stringResolver,
            \ReflectionType::class => $reflectionTypeResolver,
            \ReflectionParameter::class => $reflectionParameterResolver,
            \ReflectionProperty::class => $reflectionPropertyResolver,
            \ReflectionFunctionAbstract::class => $reflectionReturnTypeResolver,
        ]);

        $this->assertEquals(Type::template('STRING'), $resolver->resolve('foo'));
        $this->assertEquals(
            Type::template('REFLECTION_TYPE'),
            $resolver->resolve((new \ReflectionProperty(Dummy::class, 'id'))->getType()),
        );
        $this->assertEquals(
            Type::template('REFLECTION_PARAMETER'),
            $resolver->resolve((new \ReflectionMethod(Dummy::class, 'setId'))->getParameters()[0]),
        );
        $this->assertEquals(
            Type::template('REFLECTION_PROPERTY'),
            $resolver->resolve(new \ReflectionProperty(Dummy::class, 'id')),
        );
        $this->assertEquals(
            Type::template('REFLECTION_RETURN_TYPE'),
            $resolver->resolve(new \ReflectionMethod(Dummy::class, 'setId')),
        );
        $this->assertEquals(
            Type::template('REFLECTION_RETURN_TYPE'),
            $resolver->resolve(new \ReflectionFunction(strtoupper(...))),
        );
    }
}
