<?php

/*
 * This file is part of the Symphony package.
 *
 * (c) Fabien Potencier <fabien@symphony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symphony\Component\Form\Tests;

use PHPUnit\Framework\TestCase;
use Symphony\Component\Form\FormRegistry;
use Symphony\Component\Form\FormTypeGuesserChain;
use Symphony\Component\Form\ResolvedFormType;
use Symphony\Component\Form\ResolvedFormTypeFactoryInterface;
use Symphony\Component\Form\Tests\Fixtures\FormWithSameParentType;
use Symphony\Component\Form\Tests\Fixtures\RecursiveFormTypeBar;
use Symphony\Component\Form\Tests\Fixtures\RecursiveFormTypeBaz;
use Symphony\Component\Form\Tests\Fixtures\RecursiveFormTypeFoo;
use Symphony\Component\Form\Tests\Fixtures\FooSubType;
use Symphony\Component\Form\Tests\Fixtures\FooType;
use Symphony\Component\Form\Tests\Fixtures\FooTypeBarExtension;
use Symphony\Component\Form\Tests\Fixtures\FooTypeBazExtension;
use Symphony\Component\Form\Tests\Fixtures\TestExtension;

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
     * @var \PHPUnit_Framework_MockObject_MockObject|ResolvedFormTypeFactoryInterface
     */
    private $resolvedTypeFactory;

    /**
     * @var \PHPUnit_Framework_MockObject_MockObject
     */
    private $guesser1;

    /**
     * @var \PHPUnit_Framework_MockObject_MockObject
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
        $this->resolvedTypeFactory = $this->getMockBuilder('Symphony\Component\Form\ResolvedFormTypeFactory')->getMock();
        $this->guesser1 = $this->getMockBuilder('Symphony\Component\Form\FormTypeGuesserInterface')->getMock();
        $this->guesser2 = $this->getMockBuilder('Symphony\Component\Form\FormTypeGuesserInterface')->getMock();
        $this->extension1 = new TestExtension($this->guesser1);
        $this->extension2 = new TestExtension($this->guesser2);
        $this->registry = new FormRegistry(array(
            $this->extension1,
            $this->extension2,
        ), $this->resolvedTypeFactory);
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

        $this->assertSame($resolvedType, $this->registry->getType(get_class($type)));
    }

    public function testLoadUnregisteredType()
    {
        $type = new FooType();
        $resolvedType = new ResolvedFormType($type);

        $this->resolvedTypeFactory->expects($this->once())
            ->method('createResolvedType')
            ->with($type)
            ->willReturn($resolvedType);

        $this->assertSame($resolvedType, $this->registry->getType('Symphony\Component\Form\Tests\Fixtures\FooType'));
    }

    /**
     * @expectedException \Symphony\Component\Form\Exception\InvalidArgumentException
     */
    public function testFailIfUnregisteredTypeNoClass()
    {
        $this->registry->getType('Symphony\Blubb');
    }

    /**
     * @expectedException \Symphony\Component\Form\Exception\InvalidArgumentException
     */
    public function testFailIfUnregisteredTypeNoFormType()
    {
        $this->registry->getType('stdClass');
    }

    public function testGetTypeWithTypeExtensions()
    {
        $type = new FooType();
        $ext1 = new FooTypeBarExtension();
        $ext2 = new FooTypeBazExtension();
        $resolvedType = new ResolvedFormType($type, array($ext1, $ext2));

        $this->extension2->addType($type);
        $this->extension1->addTypeExtension($ext1);
        $this->extension2->addTypeExtension($ext2);

        $this->resolvedTypeFactory->expects($this->once())
            ->method('createResolvedType')
            ->with($type, array($ext1, $ext2))
            ->willReturn($resolvedType);

        $this->assertSame($resolvedType, $this->registry->getType(get_class($type)));
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
            ->with($type, array(), $parentResolvedType)
            ->willReturn($resolvedType);

        $this->assertSame($resolvedType, $this->registry->getType(get_class($type)));
    }

    /**
     * @expectedException \Symphony\Component\Form\Exception\LogicException
     * @expectedExceptionMessage Circular reference detected for form type "Symphony\Component\Form\Tests\Fixtures\FormWithSameParentType" (Symphony\Component\Form\Tests\Fixtures\FormWithSameParentType > Symphony\Component\Form\Tests\Fixtures\FormWithSameParentType).
     */
    public function testFormCannotHaveItselfAsAParent()
    {
        $type = new FormWithSameParentType();

        $this->extension2->addType($type);

        $this->registry->getType(FormWithSameParentType::class);
    }

    /**
     * @expectedException \Symphony\Component\Form\Exception\LogicException
     * @expectedExceptionMessage Circular reference detected for form type "Symphony\Component\Form\Tests\Fixtures\RecursiveFormTypeFoo" (Symphony\Component\Form\Tests\Fixtures\RecursiveFormTypeFoo > Symphony\Component\Form\Tests\Fixtures\RecursiveFormTypeBar > Symphony\Component\Form\Tests\Fixtures\RecursiveFormTypeBaz > Symphony\Component\Form\Tests\Fixtures\RecursiveFormTypeFoo).
     */
    public function testRecursiveFormDependencies()
    {
        $foo = new RecursiveFormTypeFoo();
        $bar = new RecursiveFormTypeBar();
        $baz = new RecursiveFormTypeBaz();

        $this->extension2->addType($foo);
        $this->extension2->addType($bar);
        $this->extension2->addType($baz);

        $this->registry->getType(RecursiveFormTypeFoo::class);
    }

    /**
     * @expectedException \Symphony\Component\Form\Exception\InvalidArgumentException
     */
    public function testGetTypeThrowsExceptionIfTypeNotFound()
    {
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

        $this->assertTrue($this->registry->hasType(get_class($type)));
    }

    public function testHasTypeIfFQCN()
    {
        $this->assertTrue($this->registry->hasType('Symphony\Component\Form\Tests\Fixtures\FooType'));
    }

    public function testDoesNotHaveTypeIfNonExistingClass()
    {
        $this->assertFalse($this->registry->hasType('Symphony\Blubb'));
    }

    public function testDoesNotHaveTypeIfNoFormType()
    {
        $this->assertFalse($this->registry->hasType('stdClass'));
    }

    public function testGetTypeGuesser()
    {
        $expectedGuesser = new FormTypeGuesserChain(array($this->guesser1, $this->guesser2));

        $this->assertEquals($expectedGuesser, $this->registry->getTypeGuesser());

        $registry = new FormRegistry(
            array($this->getMockBuilder('Symphony\Component\Form\FormExtensionInterface')->getMock()),
            $this->resolvedTypeFactory
        );

        $this->assertNull($registry->getTypeGuesser());
    }

    public function testGetExtensions()
    {
        $expectedExtensions = array($this->extension1, $this->extension2);

        $this->assertEquals($expectedExtensions, $this->registry->getExtensions());
    }
}
