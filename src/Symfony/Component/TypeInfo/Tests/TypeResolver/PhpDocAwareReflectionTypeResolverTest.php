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
use Symfony\Component\TypeInfo\Tests\Fixtures\Dummy;
use Symfony\Component\TypeInfo\Tests\Fixtures\DummyWithPhpDoc;
use Symfony\Component\TypeInfo\Type;
use Symfony\Component\TypeInfo\TypeContext\TypeContextFactory;
use Symfony\Component\TypeInfo\TypeResolver\PhpDocAwareReflectionTypeResolver;
use Symfony\Component\TypeInfo\TypeResolver\StringTypeResolver;
use Symfony\Component\TypeInfo\TypeResolver\TypeResolver;

class PhpDocAwareReflectionTypeResolverTest extends TestCase
{
    public function testReadPhpDoc()
    {
        $resolver = new PhpDocAwareReflectionTypeResolver(TypeResolver::create(), new StringTypeResolver(), new TypeContextFactory());
        $reflection = new \ReflectionClass(DummyWithPhpDoc::class);

        $this->assertEquals(Type::array(Type::object(Dummy::class)), $resolver->resolve($reflection->getProperty('arrayOfDummies')));
        $this->assertEquals(Type::object(Dummy::class), $resolver->resolve($reflection->getMethod('getNextDummy')));
        $this->assertEquals(Type::object(Dummy::class), $resolver->resolve($reflection->getMethod('getNextDummy')->getParameters()[0]));
    }

    public function testFallbackWhenNoPhpDoc()
    {
        $resolver = new PhpDocAwareReflectionTypeResolver(TypeResolver::create(), new StringTypeResolver(), new TypeContextFactory());
        $reflection = new \ReflectionClass(Dummy::class);

        $this->assertEquals(Type::int(), $resolver->resolve($reflection->getProperty('id')));
        $this->assertEquals(Type::int(), $resolver->resolve($reflection->getMethod('getId')));
        $this->assertEquals(Type::int(), $resolver->resolve($reflection->getMethod('setId')->getParameters()[0]));
    }
}
