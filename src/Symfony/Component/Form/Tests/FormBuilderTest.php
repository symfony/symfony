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

use Symfony\Component\Form\ButtonBuilder;
use Symfony\Component\Form\FormBuilder;
use Symfony\Component\Form\SubmitButtonBuilder;

class FormBuilderTest extends \PHPUnit_Framework_TestCase
{
    private $dispatcher;
    private $factory;
    private $builder;

    protected function setUp()
    {
        $this->dispatcher = $this->getMockBuilder('Symfony\Component\EventDispatcher\EventDispatcherInterface')->getMock();
        $this->factory = $this->getMockBuilder('Symfony\Component\Form\FormFactoryInterface')->getMock();
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
     * are not synchronized anymore.
     *
     * @see FormType::buildForm()
     */
    public function testNoSetName()
    {
        $this->assertFalse(method_exists($this->builder, 'setName'));
    }

    public function testAddNameNoStringAndNoInteger()
    {
        $this->setExpectedException('Symfony\Component\Form\Exception\UnexpectedTypeException');
        $this->builder->add(true);
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
        $builder = $this->builder->add('foo', 'Symfony\Component\Form\Extension\Core\Type\TextType', array('bar' => 'baz'));
        $this->assertSame($builder, $this->builder);
    }

    public function testAdd()
    {
        $this->assertFalse($this->builder->has('foo'));
        $this->builder->add('foo', 'Symfony\Component\Form\Extension\Core\Type\TextType');
        $this->assertTrue($this->builder->has('foo'));
    }

    public function testAddIntegerName()
    {
        $this->assertFalse($this->builder->has(0));
        $this->builder->add(0, 'Symfony\Component\Form\Extension\Core\Type\TextType');
        $this->assertTrue($this->builder->has(0));
    }

    public function testAll()
    {
        $this->factory->expects($this->once())
            ->method('createNamedBuilder')
            ->with('foo', 'Symfony\Component\Form\Extension\Core\Type\TextType')
            ->will($this->returnValue(new FormBuilder('foo', null, $this->dispatcher, $this->factory)));

        $this->assertCount(0, $this->builder->all());
        $this->assertFalse($this->builder->has('foo'));

        $this->builder->add('foo', 'Symfony\Component\Form\Extension\Core\Type\TextType');
        $children = $this->builder->all();

        $this->assertTrue($this->builder->has('foo'));
        $this->assertCount(1, $children);
        $this->assertArrayHasKey('foo', $children);
    }

    /*
     * https://github.com/symfony/symfony/issues/4693
     */
    public function testMaintainOrderOfLazyAndExplicitChildren()
    {
        $this->builder->add('foo', 'Symfony\Component\Form\Extension\Core\Type\TextType');
        $this->builder->add($this->getFormBuilder('bar'));
        $this->builder->add('baz', 'Symfony\Component\Form\Extension\Core\Type\TextType');

        $children = $this->builder->all();

        $this->assertSame(array('foo', 'bar', 'baz'), array_keys($children));
    }

    public function testAddFormType()
    {
        $this->assertFalse($this->builder->has('foo'));
        $this->builder->add('foo', $this->getMockBuilder('Symfony\Component\Form\FormTypeInterface')->getMock());
        $this->assertTrue($this->builder->has('foo'));
    }

    public function testRemove()
    {
        $this->builder->add('foo', 'Symfony\Component\Form\Extension\Core\Type\TextType');
        $this->builder->remove('foo');
        $this->assertFalse($this->builder->has('foo'));
    }

    public function testRemoveUnknown()
    {
        $this->builder->remove('foo');
        $this->assertFalse($this->builder->has('foo'));
    }

    // https://github.com/symfony/symfony/pull/4826
    public function testRemoveAndGetForm()
    {
        $this->builder->add('foo', 'Symfony\Component\Form\Extension\Core\Type\TextType');
        $this->builder->remove('foo');
        $form = $this->builder->getForm();
        $this->assertInstanceOf('Symfony\Component\Form\Form', $form);
    }

    public function testCreateNoTypeNo()
    {
        $this->factory->expects($this->once())
            ->method('createNamedBuilder')
            ->with('foo', 'Symfony\Component\Form\Extension\Core\Type\TextType', null, array())
        ;

        $this->builder->create('foo');
    }

    public function testAddButton()
    {
        $this->builder->add(new ButtonBuilder('reset'));
        $this->builder->add(new SubmitButtonBuilder('submit'));
    }

    public function testGetUnknown()
    {
        $this->setExpectedException('Symfony\Component\Form\Exception\InvalidArgumentException', 'The child with the name "foo" does not exist.');
        $this->builder->get('foo');
    }

    public function testGetExplicitType()
    {
        $expectedType = 'Symfony\Component\Form\Extension\Core\Type\TextType';
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

    public function testGetGuessedType()
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

    public function testGetFormConfigErasesReferences()
    {
        $builder = new FormBuilder('name', null, $this->dispatcher, $this->factory);
        $builder->add(new FormBuilder('child', null, $this->dispatcher, $this->factory));

        $config = $builder->getFormConfig();
        $reflClass = new \ReflectionClass($config);
        $children = $reflClass->getProperty('children');
        $unresolvedChildren = $reflClass->getProperty('unresolvedChildren');

        $children->setAccessible(true);
        $unresolvedChildren->setAccessible(true);

        $this->assertEmpty($children->getValue($config));
        $this->assertEmpty($unresolvedChildren->getValue($config));
    }

    private function getFormBuilder($name = 'name')
    {
        $mock = $this->getMockBuilder('Symfony\Component\Form\FormBuilder')
            ->disableOriginalConstructor()
            ->getMock();

        $mock->expects($this->any())
            ->method('getName')
            ->will($this->returnValue($name));

        return $mock;
    }
}
