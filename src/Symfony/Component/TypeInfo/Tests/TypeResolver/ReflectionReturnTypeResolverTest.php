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
use Symfony\Component\TypeInfo\Exception\UnsupportedException;
use Symfony\Component\TypeInfo\Tests\Fixtures\ReflectionExtractableDummy;
use Symfony\Component\TypeInfo\Type;
use Symfony\Component\TypeInfo\TypeContext\TypeContextFactory;
use Symfony\Component\TypeInfo\TypeResolver\ReflectionReturnTypeResolver;
use Symfony\Component\TypeInfo\TypeResolver\ReflectionTypeResolver;

class ReflectionReturnTypeResolverTest extends TestCase
{
    private ReflectionReturnTypeResolver $resolver;

    protected function setUp(): void
    {
        $this->resolver = new ReflectionReturnTypeResolver(new ReflectionTypeResolver(), new TypeContextFactory());
    }

    public function testCannotResolveNonReflectionFunctionAbstract()
    {
        $this->expectException(UnsupportedException::class);
        $this->resolver->resolve(123);
    }

    public function testCannotResolveReflectionFunctionAbstractWithoutType()
    {
        $this->expectException(UnsupportedException::class);

        $reflectionClass = new \ReflectionClass(ReflectionExtractableDummy::class);
        $reflectionFunction = $reflectionClass->getMethod('getNothing');

        $this->resolver->resolve($reflectionFunction);
    }

    public function testResolve()
    {
        $reflectionClass = new \ReflectionClass(ReflectionExtractableDummy::class);
        $reflectionFunction = $reflectionClass->getMethod('getBuiltin');

        $this->assertEquals(Type::int(), $this->resolver->resolve($reflectionFunction));
    }

    public function testCreateTypeContextOrUseProvided()
    {
        $reflectionClass = new \ReflectionClass(ReflectionExtractableDummy::class);
        $reflectionFunction = $reflectionClass->getMethod('getSelf');

        $this->assertEquals(Type::object(ReflectionExtractableDummy::class), $this->resolver->resolve($reflectionFunction));

        $typeContext = (new TypeContextFactory())->createFromClassName(self::class);

        $this->assertEquals(Type::object(self::class), $this->resolver->resolve($reflectionFunction, $typeContext));
    }
}
