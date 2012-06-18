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

use Symfony\Component\Form\FormBuilder;

class FormBuilderTest extends \PHPUnit_Framework_TestCase
{
    private $dispatcher;

    private $factory;

    private $builder;

    protected function setUp()
    {
        if (!class_exists('Symfony\Component\EventDispatcher\EventDispatcher')) {
            $this->markTestSkipped('The "EventDispatcher" component is not available');
        }

        $this->dispatcher = $this->getMock('Symfony\Component\EventDispatcher\EventDispatcherInterface');
        $this->factory = $this->getMock('Symfony\Component\Form\FormFactoryInterface');
        $this->builder = new FormBuilder('name', null, $this->dispatcher, $this->factory);
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
     * @see FormType::buildForm
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
        $this->builder = new FormBuilder('name', 'stdClass', $this->dispatcher, $this->factory);
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

    public function testAll()
    {
        $this->factory->expects($this->once())
            ->method('createNamedBuilder')
            ->with('foo', 'text')
            ->will($this->returnValue(new FormBuilder('foo', null, $this->dispatcher, $this->factory)));

        $this->assertCount(0, $this->builder->all());
        $this->assertFalse($this->builder->has('foo'));

        $this->builder->add('foo', 'text');
        $children = $this->builder->all();

        $this->assertTrue($this->builder->has('foo'));
        $this->assertCount(1, $children);
        $this->assertArrayHasKey('foo', $children);
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
                ->with('foo', 'text', null, array())
        ;

        $builder = $this->builder->create('foo');
    }

    public function testGetUnknown()
    {
        $this->setExpectedException('Symfony\Component\Form\Exception\FormException', 'The child with the name "foo" does not exist.');
        $this->builder->get('foo');
    }

    public function testGetTyped()
    {
        $expectedType = 'text';
        $expectedName = 'foo';
        $expectedOptions = array('bar' => 'baz');

        $this->factory->expects($this->once())
                ->method('createNamedBuilder')
                ->with($expectedName, $expectedType, null, $expectedOptions)
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

        $this->builder = new FormBuilder('name', 'stdClass', $this->dispatcher, $this->factory);
        $this->builder->add($expectedName, null, $expectedOptions);
        $builder = $this->builder->get($expectedName);

        $this->assertNotSame($builder, $this->builder);
    }

    public function testGetParent()
    {
        $this->assertNull($this->builder->getParent());
    }

    public function testGetParentForAddedBuilder()
    {
        $builder = new FormBuilder('name', null, $this->dispatcher, $this->factory);
        $this->builder->add($builder);
        $this->assertSame($this->builder, $builder->getParent());
    }

    public function testGetParentForRemovedBuilder()
    {
        $builder = new FormBuilder('name', null, $this->dispatcher, $this->factory);
        $this->builder->add($builder);
        $this->builder->remove('name');
        $this->assertNull($builder->getParent());
    }

    public function testGetParentForCreatedBuilder()
    {
        $this->builder = new FormBuilder('name', 'stdClass', $this->dispatcher, $this->factory);
        $this->factory
            ->expects($this->once())
                ->method('createNamedBuilder')
                ->with('bar', 'text', null, array(), $this->builder)
        ;

        $this->factory
            ->expects($this->once())
                ->method('createBuilderForProperty')
                ->with('stdClass', 'foo', null, array(), $this->builder)
        ;

        $this->builder->create('foo');
        $this->builder->create('bar', 'text');
    }

    private function getFormBuilder()
    {
        return $this->getMockBuilder('Symfony\Component\Form\FormBuilder')
            ->disableOriginalConstructor()
            ->getMock();
    }
}
