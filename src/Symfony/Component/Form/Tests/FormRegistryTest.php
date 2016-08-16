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

use Symfony\Component\Form\FormRegistry;
use Symfony\Component\Form\FormTypeGuesserChain;
use Symfony\Component\Form\ResolvedFormType;
use Symfony\Component\Form\ResolvedFormTypeFactoryInterface;
use Symfony\Component\Form\Tests\Fixtures\FooSubType;
use Symfony\Component\Form\Tests\Fixtures\FooType;
use Symfony\Component\Form\Tests\Fixtures\FooTypeBarExtension;
use Symfony\Component\Form\Tests\Fixtures\FooTypeBazExtension;
use Symfony\Component\Form\Tests\Fixtures\TestExtension;
use Symfony\Component\Form\Tests\Fixtures\LegacyFooSubTypeWithParentInstance;
use Symfony\Component\Form\Tests\Fixtures\LegacyFooSubType;
use Symfony\Component\Form\Tests\Fixtures\LegacyFooTypeBazExtension;
use Symfony\Component\Form\Tests\Fixtures\LegacyFooTypeBarExtension;
use Symfony\Component\Form\Tests\Fixtures\LegacyFooType;

/**
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class FormRegistryTest extends \PHPUnit_Framework_TestCase
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
        $this->resolvedTypeFactory = $this->getMock('Symfony\Component\Form\ResolvedFormTypeFactory');
        $this->guesser1 = $this->getMock('Symfony\Component\Form\FormTypeGuesserInterface');
        $this->guesser2 = $this->getMock('Symfony\Component\Form\FormTypeGuesserInterface');
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

        $this->assertSame($resolvedType, $this->registry->getType('Symfony\Component\Form\Tests\Fixtures\FooType'));
    }

    /**
     * @expectedException \Symfony\Component\Form\Exception\InvalidArgumentException
     */
    public function testFailIfUnregisteredTypeNoClass()
    {
        $this->registry->getType('Symfony\Blubb');
    }

    /**
     * @expectedException \Symfony\Component\Form\Exception\InvalidArgumentException
     */
    public function testFailIfUnregisteredTypeNoFormType()
    {
        $this->registry->getType('stdClass');
    }

    public function testLegacyGetTypeFromExtension()
    {
        $type = new LegacyFooType();
        $resolvedType = new ResolvedFormType($type);

        $this->extension2->addType($type);

        $this->resolvedTypeFactory->expects($this->once())
            ->method('createResolvedType')
            ->with($type)
            ->willReturn($resolvedType);

        $this->assertSame($resolvedType, $this->registry->getType('foo'));

        // Even types with explicit getName() methods must support access by
        // FQCN to support a smooth transition from 2.8 => 3.0
        $this->assertSame($resolvedType, $this->registry->getType(get_class($type)));
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

    public function testLegacyGetTypeWithTypeExtensions()
    {
        $type = new LegacyFooType();
        $ext1 = new LegacyFooTypeBarExtension();
        $ext2 = new LegacyFooTypeBazExtension();
        $resolvedType = new ResolvedFormType($type, array($ext1, $ext2));

        $this->extension2->addType($type);
        $this->extension1->addTypeExtension($ext1);
        $this->extension2->addTypeExtension($ext2);

        $this->resolvedTypeFactory->expects($this->once())
            ->method('createResolvedType')
            ->with($type, array($ext1, $ext2))
            ->willReturn($resolvedType);

        $this->assertSame($resolvedType, $this->registry->getType('foo'));
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

    public function testLegacyGetTypeConnectsParent()
    {
        $parentType = new LegacyFooType();
        $type = new LegacyFooSubType();
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

        $this->assertSame($resolvedType, $this->registry->getType('foo_sub_type'));
    }

    /**
     * @group legacy
     */
    public function testGetTypeConnectsParentIfGetParentReturnsInstance()
    {
        $type = new LegacyFooSubTypeWithParentInstance();
        $parentResolvedType = new ResolvedFormType($type->getParent());
        $resolvedType = new ResolvedFormType($type);

        $this->extension1->addType($type);

        $this->resolvedTypeFactory->expects($this->at(0))
            ->method('createResolvedType')
            ->with($type->getParent())
            ->willReturn($parentResolvedType);

        $this->resolvedTypeFactory->expects($this->at(1))
            ->method('createResolvedType')
            ->with($type, array(), $parentResolvedType)
            ->willReturn($resolvedType);

        $this->assertSame($resolvedType, $this->registry->getType('foo_sub_type_parent_instance'));
    }

    /**
     * @expectedException \Symfony\Component\Form\Exception\InvalidArgumentException
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

    public function testLegacyHasTypeAfterLoadingFromExtension()
    {
        $type = new LegacyFooType();
        $resolvedType = new ResolvedFormType($type);

        $this->resolvedTypeFactory->expects($this->once())
            ->method('createResolvedType')
            ->with($type)
            ->willReturn($resolvedType);

        $this->extension2->addType($type);

        $this->assertTrue($this->registry->hasType('foo'));
    }

    public function testGetTypeGuesser()
    {
        $expectedGuesser = new FormTypeGuesserChain(array($this->guesser1, $this->guesser2));

        $this->assertEquals($expectedGuesser, $this->registry->getTypeGuesser());

        $registry = new FormRegistry(
            array($this->getMock('Symfony\Component\Form\FormExtensionInterface')),
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
