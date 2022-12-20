<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Form\Tests;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Form\Exception\InvalidArgumentException;
use Symfony\Component\Form\Exception\LogicException;
use Symfony\Component\Form\FormRegistry;
use Symfony\Component\Form\FormTypeGuesserChain;
use Symfony\Component\Form\PreloadedExtension;
use Symfony\Component\Form\ResolvedFormType;
use Symfony\Component\Form\ResolvedFormTypeFactory;
use Symfony\Component\Form\Tests\Fixtures\FooSubType;
use Symfony\Component\Form\Tests\Fixtures\FooType;
use Symfony\Component\Form\Tests\Fixtures\FooTypeBarExtension;
use Symfony\Component\Form\Tests\Fixtures\FooTypeBazExtension;
use Symfony\Component\Form\Tests\Fixtures\FormWithSameParentType;
use Symfony\Component\Form\Tests\Fixtures\NullFormTypeGuesser;
use Symfony\Component\Form\Tests\Fixtures\RecursiveFormTypeBar;
use Symfony\Component\Form\Tests\Fixtures\RecursiveFormTypeBaz;
use Symfony\Component\Form\Tests\Fixtures\RecursiveFormTypeFoo;
use Symfony\Component\Form\Tests\Fixtures\TestExtension;

/**
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class FormRegistryTest extends TestCase
{
    /**
     * @var FormRegistry
     */
    private $registry;

    /**
     * @var TestExtension
     */
    private $extension1;

    /**
     * @var TestExtension
     */
    private $extension2;

    protected function setUp(): void
    {
        $this->extension1 = new TestExtension(new NullFormTypeGuesser());
        $this->extension2 = new TestExtension(new NullFormTypeGuesser());
        $this->registry = new FormRegistry([
            $this->extension1,
            $this->extension2,
        ], new ResolvedFormTypeFactory());
    }

    public function testGetTypeFromExtension()
    {
        $type = new FooType();
        $this->extension2->addType($type);

        $resolvedFormType = $this->registry->getType(FooType::class);

        self::assertInstanceOf(ResolvedFormType::class, $resolvedFormType);
        self::assertSame($type, $resolvedFormType->getInnerType());
    }

    public function testLoadUnregisteredType()
    {
        $type = new FooType();

        $resolvedFormType = $this->registry->getType(FooType::class);

        self::assertInstanceOf(ResolvedFormType::class, $resolvedFormType);
        self::assertInstanceOf(FooType::class, $resolvedFormType->getInnerType());
        self::assertNotSame($type, $resolvedFormType->getInnerType());
    }

    public function testFailIfUnregisteredTypeNoClass()
    {
        self::expectException(InvalidArgumentException::class);
        $this->registry->getType('Symfony\Blubb');
    }

    public function testFailIfUnregisteredTypeNoFormType()
    {
        self::expectException(InvalidArgumentException::class);
        $this->registry->getType('stdClass');
    }

    public function testGetTypeWithTypeExtensions()
    {
        $type = new FooType();
        $ext1 = new FooTypeBarExtension();
        $ext2 = new FooTypeBazExtension();

        $this->extension2->addType($type);
        $this->extension1->addTypeExtension($ext1);
        $this->extension2->addTypeExtension($ext2);

        $resolvedFormType = $this->registry->getType(FooType::class);

        self::assertInstanceOf(ResolvedFormType::class, $resolvedFormType);
        self::assertSame($type, $resolvedFormType->getInnerType());
        self::assertSame([$ext1, $ext2], $resolvedFormType->getTypeExtensions());
    }

    public function testGetTypeConnectsParent()
    {
        $parentType = new FooType();
        $type = new FooSubType();

        $this->extension1->addType($parentType);
        $this->extension2->addType($type);

        $resolvedFormType = $this->registry->getType(FooSubType::class);

        self::assertInstanceOf(ResolvedFormType::class, $resolvedFormType);
        self::assertSame($type, $resolvedFormType->getInnerType());

        $resolvedParentFormType = $resolvedFormType->getParent();

        self::assertInstanceOf(ResolvedFormType::class, $resolvedParentFormType);
        self::assertSame($parentType, $resolvedParentFormType->getInnerType());
    }

    public function testFormCannotHaveItselfAsAParent()
    {
        self::expectException(LogicException::class);
        self::expectExceptionMessage('Circular reference detected for form type "Symfony\Component\Form\Tests\Fixtures\FormWithSameParentType" (Symfony\Component\Form\Tests\Fixtures\FormWithSameParentType > Symfony\Component\Form\Tests\Fixtures\FormWithSameParentType).');
        $type = new FormWithSameParentType();

        $this->extension2->addType($type);

        $this->registry->getType(FormWithSameParentType::class);
    }

    public function testRecursiveFormDependencies()
    {
        self::expectException(LogicException::class);
        self::expectExceptionMessage('Circular reference detected for form type "Symfony\Component\Form\Tests\Fixtures\RecursiveFormTypeFoo" (Symfony\Component\Form\Tests\Fixtures\RecursiveFormTypeFoo > Symfony\Component\Form\Tests\Fixtures\RecursiveFormTypeBar > Symfony\Component\Form\Tests\Fixtures\RecursiveFormTypeBaz > Symfony\Component\Form\Tests\Fixtures\RecursiveFormTypeFoo).');
        $foo = new RecursiveFormTypeFoo();
        $bar = new RecursiveFormTypeBar();
        $baz = new RecursiveFormTypeBaz();

        $this->extension2->addType($foo);
        $this->extension2->addType($bar);
        $this->extension2->addType($baz);

        $this->registry->getType(RecursiveFormTypeFoo::class);
    }

    public function testGetTypeThrowsExceptionIfTypeNotFound()
    {
        self::expectException(InvalidArgumentException::class);
        $this->registry->getType('bar');
    }

    public function testHasTypeAfterLoadingFromExtension()
    {
        $type = new FooType();
        $this->extension2->addType($type);

        self::assertTrue($this->registry->hasType(FooType::class));
    }

    public function testHasTypeIfFQCN()
    {
        self::assertTrue($this->registry->hasType(FooType::class));
    }

    public function testDoesNotHaveTypeIfNonExistingClass()
    {
        self::assertFalse($this->registry->hasType('Symfony\Blubb'));
    }

    public function testDoesNotHaveTypeIfNoFormType()
    {
        self::assertFalse($this->registry->hasType('stdClass'));
    }

    public function testGetTypeGuesser()
    {
        $expectedGuesser = new FormTypeGuesserChain([new NullFormTypeGuesser(), new NullFormTypeGuesser()]);

        self::assertEquals($expectedGuesser, $this->registry->getTypeGuesser());

        $registry = new FormRegistry([new PreloadedExtension([], [])], new ResolvedFormTypeFactory());

        self::assertNull($registry->getTypeGuesser());
    }

    public function testGetExtensions()
    {
        $expectedExtensions = [$this->extension1, $this->extension2];

        self::assertEquals($expectedExtensions, $this->registry->getExtensions());
    }
}
