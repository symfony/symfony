<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\TypeInfo\Tests\TypeContext;

use PHPUnit\Framework\TestCase;
use Symfony\Component\TypeInfo\Tests\Fixtures\AbstractDummy;
use Symfony\Component\TypeInfo\Tests\Fixtures\Dummy;
use Symfony\Component\TypeInfo\Tests\Fixtures\DummyWithTemplates;
use Symfony\Component\TypeInfo\Tests\Fixtures\DummyWithUses;
use Symfony\Component\TypeInfo\Type;
use Symfony\Component\TypeInfo\TypeContext\TypeContextFactory;
use Symfony\Component\TypeInfo\TypeResolver\StringTypeResolver;

class TypeContextFactoryTest extends TestCase
{
    private TypeContextFactory $typeContextFactory;

    protected function setUp(): void
    {
        $this->typeContextFactory = new TypeContextFactory(new StringTypeResolver());
    }

    public function testCollectClassNames()
    {
        $typeContext = $this->typeContextFactory->createFromClassName(Dummy::class, AbstractDummy::class);
        $this->assertSame('Dummy', $typeContext->calledClassName);
        $this->assertSame('AbstractDummy', $typeContext->declaringClassName);

        $typeContext = $this->typeContextFactory->createFromReflection(new \ReflectionClass(Dummy::class));
        $this->assertSame('Dummy', $typeContext->calledClassName);
        $this->assertSame('Dummy', $typeContext->declaringClassName);

        $typeContext = $this->typeContextFactory->createFromReflection(new \ReflectionProperty(Dummy::class, 'id'));
        $this->assertSame('Dummy', $typeContext->calledClassName);
        $this->assertSame('Dummy', $typeContext->declaringClassName);

        $typeContext = $this->typeContextFactory->createFromReflection(new \ReflectionMethod(Dummy::class, 'getId'));
        $this->assertSame('Dummy', $typeContext->calledClassName);
        $this->assertSame('Dummy', $typeContext->declaringClassName);

        $typeContext = $this->typeContextFactory->createFromReflection(new \ReflectionParameter([Dummy::class, 'setId'], 'id'));
        $this->assertSame('Dummy', $typeContext->calledClassName);
        $this->assertSame('Dummy', $typeContext->declaringClassName);
    }

    public function testCollectNamespace()
    {
        $namespace = 'Symfony\\Component\\TypeInfo\\Tests\\Fixtures';

        $this->assertSame($namespace, $this->typeContextFactory->createFromClassName(Dummy::class)->namespace);

        $this->assertEquals($namespace, $this->typeContextFactory->createFromReflection(new \ReflectionClass(Dummy::class))->namespace);
        $this->assertEquals($namespace, $this->typeContextFactory->createFromReflection(new \ReflectionProperty(Dummy::class, 'id'))->namespace);
        $this->assertEquals($namespace, $this->typeContextFactory->createFromReflection(new \ReflectionMethod(Dummy::class, 'getId'))->namespace);
        $this->assertEquals($namespace, $this->typeContextFactory->createFromReflection(new \ReflectionParameter([Dummy::class, 'setId'], 'id'))->namespace);
    }

    public function testCollectUses()
    {
        $this->assertSame([], $this->typeContextFactory->createFromClassName(Dummy::class)->uses);

        $uses = [
            'Type' => Type::class,
            \DateTimeInterface::class => '\\'.\DateTimeInterface::class,
            'DateTime' => '\\'.\DateTimeImmutable::class,
        ];

        $this->assertSame($uses, $this->typeContextFactory->createFromClassName(DummyWithUses::class)->uses);

        $this->assertEquals($uses, $this->typeContextFactory->createFromReflection(new \ReflectionClass(DummyWithUses::class))->uses);
        $this->assertEquals($uses, $this->typeContextFactory->createFromReflection(new \ReflectionProperty(DummyWithUses::class, 'createdAt'))->uses);
        $this->assertEquals($uses, $this->typeContextFactory->createFromReflection(new \ReflectionMethod(DummyWithUses::class, 'setCreatedAt'))->uses);
        $this->assertEquals($uses, $this->typeContextFactory->createFromReflection(new \ReflectionParameter([DummyWithUses::class, 'setCreatedAt'], 'createdAt'))->uses);
    }

    public function testCollectTemplates()
    {
        $this->assertEquals([], $this->typeContextFactory->createFromClassName(Dummy::class)->templates);
        $this->assertEquals([
            'T' => Type::union(Type::int(), Type::string()),
            'U' => Type::mixed(),
        ], $this->typeContextFactory->createFromClassName(DummyWithTemplates::class)->templates);

        $this->assertEquals([
            'T' => Type::union(Type::int(), Type::string()),
            'U' => Type::mixed(),
        ], $this->typeContextFactory->createFromReflection(new \ReflectionClass(DummyWithTemplates::class))->templates);

        $this->assertEquals([
            'T' => Type::union(Type::int(), Type::string()),
            'U' => Type::mixed(),
        ], $this->typeContextFactory->createFromReflection(new \ReflectionProperty(DummyWithTemplates::class, 'price'))->templates);

        $this->assertEquals([
            'T' => Type::union(Type::int(), Type::float()),
            'U' => Type::mixed(),
            'V' => Type::mixed(),
        ], $this->typeContextFactory->createFromReflection(new \ReflectionMethod(DummyWithTemplates::class, 'getPrice'))->templates);

        $this->assertEquals([
            'T' => Type::union(Type::int(), Type::float()),
            'U' => Type::mixed(),
            'V' => Type::mixed(),
        ], $this->typeContextFactory->createFromReflection(new \ReflectionParameter([DummyWithTemplates::class, 'getPrice'], 'inCents'))->templates);
    }

    public function testDoNotCollectTemplatesWhenToStringTypeResolver()
    {
        $typeContextFactory = new TypeContextFactory();

        $this->assertEquals([], $typeContextFactory->createFromClassName(DummyWithTemplates::class)->templates);
    }
}
