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

use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Form\FormRegistry;
use Symfony\Component\Form\FormTypeGuesserChain;
use Symfony\Component\Form\ResolvedFormType;
use Symfony\Component\Form\ResolvedFormTypeFactoryInterface;
use Symfony\Component\Form\Tests\Fixtures\FooSubType;
use Symfony\Component\Form\Tests\Fixtures\FooType;
use Symfony\Component\Form\Tests\Fixtures\FooTypeBarExtension;
use Symfony\Component\Form\Tests\Fixtures\FooTypeBazExtension;
use Symfony\Component\Form\Tests\Fixtures\FormWithSameParentType;
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
     * @var MockObject|ResolvedFormTypeFactoryInterface
     */
    private $resolvedTypeFactory;

    /**
     * @var MockObject
     */
    private $guesser1;

    /**
     * @var MockObject
     */
    private $guesser2;

    /**
     * @var TestExtension
     */
    private $extension1;

    /**
     * @var TestExtension
     */
    private $extension2;

    protected function setUp()
    {
        $this->resolvedTypeFactory = $this->getMockBuilder('Symfony\Component\Form\ResolvedFormTypeFactory')->getMock();
        $this->guesser1 = $this->getMockBuilder('Symfony\Component\Form\FormTypeGuesserInterface')->getMock();
        $this->guesser2 = $this->getMockBuilder('Symfony\Component\Form\FormTypeGuesserInterface')->getMock();
        $this->extension1 = new TestExtension($this->guesser1);
        $this->extension2 = new TestExtension($this->guesser2);
        $this->registry = new FormRegistry([
            $this->extension1,
            $this->extension2,
        ], $this->resolvedTypeFactory);
    }

    public function testGetTypeFromExtension()
    {
        $type = new FooType();
        $resolvedType = new ResolvedFormType($type);

        $this->extension2->addType($type);

        $this->resolvedTypeFactory->expects($this->once())
            ->method('createResolvedType')
            ->with($type)
            ->willReturn($resolvedType);

        $this->assertSame($resolvedType, $this->registry->getType(\get_class($type)));
    }

    public function testLoadUnregisteredType()
    {
        $type = new FooType();
        $resolvedType = new ResolvedFormType($type);

        $this->resolvedTypeFactory->expects($this->once())
            ->method('createResolvedType')
            ->with($type)
            ->willReturn($resolvedType);

        $this->assertSame($resolvedType, $this->registry->getType('Symfony\Component\Form\Tests\Fixtures\FooType'));
    }

    public function testFailIfUnregisteredTypeNoClass()
    {
        $this->expectException('Symfony\Component\Form\Exception\InvalidArgumentException');
        $this->registry->getType('Symfony\Blubb');
    }

    public function testFailIfUnregisteredTypeNoFormType()
    {
        $this->expectException('Symfony\Component\Form\Exception\InvalidArgumentException');
        $this->registry->getType('stdClass');
    }

    public function testGetTypeWithTypeExtensions()
    {
        $type = new FooType();
        $ext1 = new FooTypeBarExtension();
        $ext2 = new FooTypeBazExtension();
        $resolvedType = new ResolvedFormType($type, [$ext1, $ext2]);

        $this->extension2->addType($type);
        $this->extension1->addTypeExtension($ext1);
        $this->extension2->addTypeExtension($ext2);

        $this->resolvedTypeFactory->expects($this->once())
            ->method('createResolvedType')
            ->with($type, [$ext1, $ext2])
            ->willReturn($resolvedType);

        $this->assertSame($resolvedType, $this->registry->getType(\get_class($type)));
    }

    public function testGetTypeConnectsParent()
    {
        $parentType = new FooType();
        $type = new FooSubType();
        $parentResolvedType = new ResolvedFormType($parentType);
        $resolvedType = new ResolvedFormType($type);

        $this->extension1->addType($parentType);
        $this->extension2->addType($type);

        $this->resolvedTypeFactory->expects($this->at(0))
            ->method('createResolvedType')
            ->with($parentType)
            ->willReturn($parentResolvedType);

        $this->resolvedTypeFactory->expects($this->at(1))
            ->method('createResolvedType')
            ->with($type, [], $parentResolvedType)
            ->willReturn($resolvedType);

        $this->assertSame($resolvedType, $this->registry->getType(\get_class($type)));
    }

    public function testFormCannotHaveItselfAsAParent()
    {
        $this->expectException('Symfony\Component\Form\Exception\LogicException');
        $this->expectExceptionMessage('Circular reference detected for form type "Symfony\Component\Form\Tests\Fixtures\FormWithSameParentType" (Symfony\Component\Form\Tests\Fixtures\FormWithSameParentType > Symfony\Component\Form\Tests\Fixtures\FormWithSameParentType).');
        $type = new FormWithSameParentType();

        $this->extension2->addType($type);

        $this->registry->getType(FormWithSameParentType::class);
    }

    public function testRecursiveFormDependencies()
    {
        $this->expectException('Symfony\Component\Form\Exception\LogicException');
        $this->expectExceptionMessage('Circular reference detected for form type "Symfony\Component\Form\Tests\Fixtures\RecursiveFormTypeFoo" (Symfony\Component\Form\Tests\Fixtures\RecursiveFormTypeFoo > Symfony\Component\Form\Tests\Fixtures\RecursiveFormTypeBar > Symfony\Component\Form\Tests\Fixtures\RecursiveFormTypeBaz > Symfony\Component\Form\Tests\Fixtures\RecursiveFormTypeFoo).');
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
        $this->expectException('Symfony\Component\Form\Exception\InvalidArgumentException');
        $this->registry->getType('bar');
    }

    public function testHasTypeAfterLoadingFromExtension()
    {
        $type = new FooType();
        $resolvedType = new ResolvedFormType($type);

        $this->resolvedTypeFactory->expects($this->once())
            ->method('createResolvedType')
            ->with($type)
            ->willReturn($resolvedType);

        $this->extension2->addType($type);

        $this->assertTrue($this->registry->hasType(\get_class($type)));
    }

    public function testHasTypeIfFQCN()
    {
        $this->assertTrue($this->registry->hasType('Symfony\Component\Form\Tests\Fixtures\FooType'));
    }

    public function testDoesNotHaveTypeIfNonExistingClass()
    {
        $this->assertFalse($this->registry->hasType('Symfony\Blubb'));
    }

    public function testDoesNotHaveTypeIfNoFormType()
    {
        $this->assertFalse($this->registry->hasType('stdClass'));
    }

    public function testGetTypeGuesser()
    {
        $expectedGuesser = new FormTypeGuesserChain([$this->guesser1, $this->guesser2]);

        $this->assertEquals($expectedGuesser, $this->registry->getTypeGuesser());

        $registry = new FormRegistry(
            [$this->getMockBuilder('Symfony\Component\Form\FormExtensionInterface')->getMock()],
            $this->resolvedTypeFactory
        );

        $this->assertNull($registry->getTypeGuesser());
    }

    public function testGetExtensions()
    {
        $expectedExtensions = [$this->extension1, $this->extension2];

        $this->assertEquals($expectedExtensions, $this->registry->getExtensions());
    }
}
