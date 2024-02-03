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
use Symfony\Component\DependencyInjection\ServiceLocator;
use Symfony\Component\TypeInfo\Exception\UnsupportedException;
use Symfony\Component\TypeInfo\Tests\Fixtures\Dummy;
use Symfony\Component\TypeInfo\Type;
use Symfony\Component\TypeInfo\TypeResolver\TypeResolver;
use Symfony\Component\TypeInfo\TypeResolver\TypeResolverInterface;

class TypeResolverTest extends TestCase
{
    public function testResolve()
    {
        $resolver = TypeResolver::create();

        $this->assertEquals(Type::bool(), $resolver->resolve('bool'));
        $this->assertEquals(Type::int(), $resolver->resolve((new \ReflectionProperty(Dummy::class, 'id'))->getType()));
        $this->assertEquals(Type::int(), $resolver->resolve((new \ReflectionMethod(Dummy::class, 'setId'))->getParameters()[0]));
        $this->assertEquals(Type::int(), $resolver->resolve(new \ReflectionProperty(Dummy::class, 'id')));
        $this->assertEquals(Type::void(), $resolver->resolve(new \ReflectionMethod(Dummy::class, 'setId')));
        $this->assertEquals(Type::string(), $resolver->resolve(new \ReflectionFunction(strtoupper(...))));
    }

    public function testCannotFindResolver()
    {
        $this->expectException(UnsupportedException::class);
        $this->expectExceptionMessage('Cannot find any resolver for "int" type.');

        $resolver = new TypeResolver(new ServiceLocator([]));
        $resolver->resolve(1);
    }

    public function testUseProperResolver()
    {
        $stringResolver = $this->createMock(TypeResolverInterface::class);
        $stringResolver->method('resolve')->willReturn(Type::template('STRING'));

        $reflectionTypeResolver = $this->createMock(TypeResolverInterface::class);
        $reflectionTypeResolver->method('resolve')->willReturn(Type::template('REFLECTION_TYPE'));

        $reflectionParameterResolver = $this->createMock(TypeResolverInterface::class);
        $reflectionParameterResolver->method('resolve')->willReturn(Type::template('REFLECTION_PARAMETER'));

        $reflectionPropertyResolver = $this->createMock(TypeResolverInterface::class);
        $reflectionPropertyResolver->method('resolve')->willReturn(Type::template('REFLECTION_PROPERTY'));

        $reflectionReturnTypeResolver = $this->createMock(TypeResolverInterface::class);
        $reflectionReturnTypeResolver->method('resolve')->willReturn(Type::template('REFLECTION_RETURN_TYPE'));

        $resolver = new TypeResolver(new ServiceLocator([
            'string' => fn () => $stringResolver,
            \ReflectionType::class => fn () => $reflectionTypeResolver,
            \ReflectionParameter::class => fn () => $reflectionParameterResolver,
            \ReflectionProperty::class => fn () => $reflectionPropertyResolver,
            \ReflectionFunctionAbstract::class => fn () => $reflectionReturnTypeResolver,
        ]));

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
