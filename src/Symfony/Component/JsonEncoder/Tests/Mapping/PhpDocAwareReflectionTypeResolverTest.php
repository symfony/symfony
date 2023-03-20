<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\JsonEncoder\Tests\Mapping;

use PHPUnit\Framework\TestCase;
use Symfony\Component\JsonEncoder\Mapping\PhpDocAwareReflectionTypeResolver;
use Symfony\Component\JsonEncoder\Tests\Fixtures\Model\DummyWithNameAttributes;
use Symfony\Component\JsonEncoder\Tests\Fixtures\Model\DummyWithPhpDoc;
use Symfony\Component\TypeInfo\Type;
use Symfony\Component\TypeInfo\TypeContext\TypeContextFactory;
use Symfony\Component\TypeInfo\TypeResolver\TypeResolver;

class PhpDocAwareReflectionTypeResolverTest extends TestCase
{
    public function testReadPhpDoc()
    {
        $resolver = new PhpDocAwareReflectionTypeResolver(TypeResolver::create(), new TypeContextFactory());
        $reflection = new \ReflectionClass(DummyWithPhpDoc::class);

        $this->assertEquals(Type::array(Type::object(DummyWithNameAttributes::class)), $resolver->resolve($reflection->getProperty('arrayOfDummies')));
        $this->assertEquals(Type::array(Type::string()), $resolver->resolve($reflection->getMethod('castArrayOfDummiesToArrayOfStrings')));
        $this->assertEquals(Type::array(Type::object(DummyWithNameAttributes::class)), $resolver->resolve($reflection->getMethod('castArrayOfDummiesToArrayOfStrings')->getParameters()[0]));
    }

    public function testFallbackWhenNoPhpDoc()
    {
        $resolver = new PhpDocAwareReflectionTypeResolver(TypeResolver::create(), new TypeContextFactory());
        $reflection = new \ReflectionClass(DummyWithPhpDoc::class);

        $this->assertEquals(Type::array(), $resolver->resolve($reflection->getProperty('array')));
        $this->assertEquals(Type::int(), $resolver->resolve($reflection->getMethod('countArray')));
        $this->assertEquals(Type::array(), $resolver->resolve($reflection->getMethod('countArray')->getParameters()[0]));
    }
}
