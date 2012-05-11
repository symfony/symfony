<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Tests\Component\Form;

use Symfony\Component\Form\FormBuilder;
use Symfony\Component\Form\Guess\Guess;
use Symfony\Tests\Component\Form\Fixtures\FooType;

class FormBuilderTest extends \PHPUnit_Framework_TestCase
{
    private $dispatcher;

    private $factory;

    private $builder;

    public function setUp()
    {
        $this->dispatcher = $this->getMock('Symfony\Component\EventDispatcher\EventDispatcherInterface');
        $this->factory = $this->getMock('Symfony\Component\Form\FormFactoryInterface');
        $this->builder = new FormBuilder('name', $this->factory, $this->dispatcher);
    }

    protected function tearDown()
    {
        $this->dispatcher = null;
        $this->factory = null;
        $this->builder = null;
    }

    /**
     * Changing the name is not allowed, otherwise the name and property path
     * are not synchronized anymore
     *
     * @see FieldType::buildForm
     */
    public function testNoSetName()
    {
        $this->assertFalse(method_exists($this->builder, 'setName'));
    }

    public function testAddNameNoString()
    {
        $this->setExpectedException('Symfony\Component\Form\Exception\UnexpectedTypeException');
        $this->builder->add(1234);
    }

    public function testAddTypeNoString()
    {
        $this->setExpectedException('Symfony\Component\Form\Exception\UnexpectedTypeException');
        $this->builder->add('foo', 1234);
    }

    public function testAddWithGuessFluent()
    {
        $this->builder = new FormBuilder('name', $this->factory, $this->dispatcher, 'stdClass');
        $builder = $this->builder->add('foo');
        $this->assertSame($builder, $this->builder);
    }

    public function testAddIsFluent()
    {
        $builder = $this->builder->add('foo', 'text', array('bar' => 'baz'));
        $this->assertSame($builder, $this->builder);
    }

    public function testAdd()
    {
        $this->assertFalse($this->builder->has('foo'));
        $this->builder->add('foo', 'text');
        $this->assertTrue($this->builder->has('foo'));
    }

    public function testAddFormType()
    {
        $this->assertFalse($this->builder->has('foo'));
        $this->builder->add('foo', $this->getMock('Symfony\Component\Form\FormTypeInterface'));
        $this->assertTrue($this->builder->has('foo'));
    }

    public function testRemove()
    {
        $this->builder->add('foo', 'text');
        $this->builder->remove('foo');
        $this->assertFalse($this->builder->has('foo'));
    }

    public function testRemoveUnknown()
    {
        $this->builder->remove('foo');
        $this->assertFalse($this->builder->has('foo'));
    }

    public function testCreateNoTypeNoDataClass()
    {
        $this->factory->expects($this->once())
                ->method('createNamedBuilder')
                ->with('text', 'foo', null, array())
        ;

        $builder = $this->builder->create('foo');
    }

    public function testGetUnknown()
    {
        $this->setExpectedException('Symfony\Component\Form\Exception\FormException', 'The field "foo" does not exist');
        $this->builder->get('foo');
    }

    public function testGetTyped()
    {
        $expectedType = 'text';
        $expectedName = 'foo';
        $expectedOptions = array('bar' => 'baz');

        $this->factory->expects($this->once())
                ->method('createNamedBuilder')
                ->with($expectedType, $expectedName, null, $expectedOptions)
                ->will($this->returnValue($this->getFormBuilder()));

        $this->builder->add($expectedName, $expectedType, $expectedOptions);
        $builder = $this->builder->get($expectedName);

        $this->assertNotSame($builder, $this->builder);
    }

    public function testGetGuessed()
    {
        $expectedName = 'foo';
        $expectedOptions = array('bar' => 'baz');

        $this->factory->expects($this->once())
                ->method('createBuilderForProperty')
                ->with('stdClass', $expectedName, null, $expectedOptions)
                ->will($this->returnValue($this->getFormBuilder()));

        $this->builder = new FormBuilder('name', $this->factory, $this->dispatcher, 'stdClass');
        $this->builder->add($expectedName, null, $expectedOptions);
        $builder = $this->builder->get($expectedName);

        $this->assertNotSame($builder, $this->builder);
    }

    /**
     * Adding a fooType as a child of a fooType represent a circular reference if they have the same name
     */
    public function testCircularReferenceWithSameParentAndChild()
    {
        $this->setExpectedException('Symfony\Component\Form\Exception\CircularReferenceException');

        $this->builder->setCurrentLoadingType('foo');
        $this->builder->add('name', new FooType());
    }

    /**
     * Adding a fooType as a child of a fooType doesn't represent a circular reference if they have the different names
     */
    public function testCircularReferenceWithDifferentParentAndChild()
    {
        $foo = new FooType();

        $this->factory->expects($this->once())
            ->method('createNamedBuilder')
            ->with($foo, 'another_name', null, array())
            ->will($this->returnValue($foo));
        ;

        $this->builder->setCurrentLoadingType('foo');
        $this->builder->add('another_name', $foo);

        $this->assertEquals($foo->getName(), $this->builder->get('another_name')->getName());
    }

    private function getFormBuilder()
    {
        return $this->getMockBuilder('Symfony\Component\Form\FormBuilder')
            ->disableOriginalConstructor()
            ->getMock();
    }
}
