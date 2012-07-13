<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Form;

use Symfony\Component\Form\Tests\Fixtures\TestExtension;
use Symfony\Component\Form\Tests\Fixtures\FooSubType;
use Symfony\Component\Form\Tests\Fixtures\FooTypeBazExtension;
use Symfony\Component\Form\Tests\Fixtures\FooTypeBarExtension;
use Symfony\Component\Form\Tests\Fixtures\FooType;

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
        $this->guesser1 = $this->getMock('Symfony\Component\Form\FormTypeGuesserInterface');
        $this->guesser2 = $this->getMock('Symfony\Component\Form\FormTypeGuesserInterface');
        $this->extension1 = new TestExtension($this->guesser1);
        $this->extension2 = new TestExtension($this->guesser2);
        $this->registry = new FormRegistry(array(
            $this->extension1,
            $this->extension2,
        ));
    }

    public function testResolveType()
    {
        $type = new FooType();
        $ext1 = new FooTypeBarExtension();
        $ext2 = new FooTypeBazExtension();

        $this->extension1->addTypeExtension($ext1);
        $this->extension2->addTypeExtension($ext2);

        $resolvedType = $this->registry->resolveType($type);

        $this->assertEquals($type, $resolvedType->getInnerType());
        $this->assertEquals(array($ext1, $ext2), $resolvedType->getTypeExtensions());
    }

    public function testResolveTypeConnectsParent()
    {
        $parentType = new FooType();
        $type = new FooSubType();

        $resolvedParentType = $this->registry->resolveType($parentType);

        $this->registry->addType($resolvedParentType);

        $resolvedType = $this->registry->resolveType($type);

        $this->assertSame($resolvedParentType, $resolvedType->getParent());
    }

    /**
     * @expectedException Symfony\Component\Form\Exception\FormException
     */
    public function testResolveTypeThrowsExceptionIfParentNotFound()
    {
        $type = new FooSubType();

        $this->registry->resolveType($type);
    }

    public function testGetTypeReturnsAddedType()
    {
        $type = new FooType();

        $resolvedType = $this->registry->resolveType($type);

        $this->registry->addType($resolvedType);

        $this->assertSame($resolvedType, $this->registry->getType('foo'));
    }

    public function testGetTypeFromExtension()
    {
        $type = new FooType();

        $this->extension2->addType($type);

        $resolvedType = $this->registry->getType('foo');

        $this->assertInstanceOf('Symfony\Component\Form\ResolvedFormTypeInterface', $resolvedType);
        $this->assertSame($type, $resolvedType->getInnerType());
    }

    /**
     * @expectedException Symfony\Component\Form\Exception\FormException
     */
    public function testGetTypeThrowsExceptionIfTypeNotFound()
    {
        $this->registry->getType('bar');
    }

    /**
     * @expectedException Symfony\Component\Form\Exception\UnexpectedTypeException
     */
    public function testGetTypeThrowsExceptionIfNoString()
    {
        $this->registry->getType(array());
    }

    public function testHasTypeAfterAdding()
    {
        $type = new FooType();

        $resolvedType = $this->registry->resolveType($type);

        $this->assertFalse($this->registry->hasType('foo'));

        $this->registry->addType($resolvedType);

        $this->assertTrue($this->registry->hasType('foo'));
    }

    public function testHasTypeAfterLoadingFromExtension()
    {
        $type = new FooType();

        $this->assertFalse($this->registry->hasType('foo'));

        $this->extension2->addType($type);

        $this->assertTrue($this->registry->hasType('foo'));
    }

    public function testGetTypeGuesser()
    {
        $expectedGuesser = new FormTypeGuesserChain(array($this->guesser1, $this->guesser2));

        $this->assertEquals($expectedGuesser, $this->registry->getTypeGuesser());
    }

    public function testGetExtensions()
    {
        $expectedExtensions = array($this->extension1, $this->extension2);

        $this->assertEquals($expectedExtensions, $this->registry->getExtensions());
    }
}
