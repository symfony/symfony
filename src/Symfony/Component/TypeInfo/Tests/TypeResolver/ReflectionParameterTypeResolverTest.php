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
use Symfony\Component\TypeInfo\TypeResolver\ReflectionParameterTypeResolver;
use Symfony\Component\TypeInfo\TypeResolver\ReflectionTypeResolver;

class ReflectionParameterTypeResolverTest extends TestCase
{
    private ReflectionParameterTypeResolver $resolver;

    protected function setUp(): void
    {
        $this->resolver = new ReflectionParameterTypeResolver(new ReflectionTypeResolver(), new TypeContextFactory());
    }

    public function testCannotResolveNonReflectionParameter()
    {
        $this->expectException(UnsupportedException::class);
        $this->resolver->resolve(123);
    }

    public function testCannotResolveReflectionParameterWithoutType()
    {
        $this->expectException(UnsupportedException::class);

        $reflectionClass = new \ReflectionClass(ReflectionExtractableDummy::class);
        $reflectionParameter = $reflectionClass->getMethod('setNothing')->getParameters()[0];

        $this->resolver->resolve($reflectionParameter);
    }

    public function testResolve()
    {
        $reflectionClass = new \ReflectionClass(ReflectionExtractableDummy::class);
        $reflectionParameter = $reflectionClass->getMethod('setBuiltin')->getParameters()[0];

        $this->assertEquals(Type::int(), $this->resolver->resolve($reflectionParameter));
    }

    public function testResolveOptionalParameter()
    {
        $reflectionClass = new \ReflectionClass(ReflectionExtractableDummy::class);
        $reflectionParameter = $reflectionClass->getMethod('setOptional')->getParameters()[0];

        $this->assertEquals(Type::nullable(Type::int()), $this->resolver->resolve($reflectionParameter));
    }

    public function testCreateTypeContextOrUseProvided()
    {
        $reflectionClass = new \ReflectionClass(ReflectionExtractableDummy::class);
        $reflectionParameter = $reflectionClass->getMethod('setSelf')->getParameters()[0];

        $this->assertEquals(Type::object(ReflectionExtractableDummy::class), $this->resolver->resolve($reflectionParameter));

        $typeContext = (new TypeContextFactory())->createFromClassName(self::class);

        $this->assertEquals(Type::object(self::class), $this->resolver->resolve($reflectionParameter, $typeContext));
    }
}
