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

use Symfony\Component\Form\FormFactory;
use Symfony\Component\Form\FormBuilder;
use Symfony\Component\Form\Type\Guesser\Guess;
use Symfony\Component\Form\Type\Guesser\ValueGuess;
use Symfony\Component\Form\Type\Guesser\TypeGuess;

class FormBuilderTest extends \PHPUnit_Framework_TestCase
{
    private $dispatcher;

    private $builder;

    public function setUp()
    {
        $this->dispatcher = $this->getMock('Symfony\Component\EventDispatcher\EventDispatcherInterface');
        $this->builder = new FormBuilder($this->dispatcher);
    }

    public function testAddNameNoString()
    {
        $this->setExpectedException('Symfony\Component\Form\Exception\UnexpectedTypeException');
        $this->builder->add(1234);
    }

    public function testAddTypeNoString()
    {
        $this->setExpectedException('Symfony\Component\Form\Exception\UnexpectedTypeException');
        $this->builder->add("foo", 1234);
    }

    public function testAddWithGuessFluent()
    {
        $this->builder = new FormBuilder($this->dispatcher, 'stdClass');
        $builder = $this->builder->add('foo');
        $this->assertSame($builder, $this->builder);
    }

    public function testAddIsFluent()
    {
        $builder = $this->builder->add("foo", "text", array("bar" => "baz"));
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
        $this->builder->add('foo', $this->getMock('Symfony\Component\Form\Type\FormTypeInterface'));
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

    public function testBuildNoTypeNoDataClass()
    {
        $this->setExpectedException('Symfony\Component\Form\Exception\FormException', 'The data class must be set to automatically create children');
        $this->builder->build("foo");
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

        $factory = $this->getMock('Symfony\Component\Form\FormFactoryInterface');
        $factory->expects($this->once())
                ->method('createBuilder')
                ->with($this->equalTo($expectedType), $this->equalTo($expectedName), $this->equalTo($expectedOptions))
                ->will($this->returnValue($this->getFormBuilder()));
        $this->builder->setFormFactory($factory);

        $this->builder->add($expectedName, $expectedType, $expectedOptions);
        $builder = $this->builder->get($expectedName);

        $this->assertNotSame($builder, $this->builder);
    }

    public function testGetGuessed()
    {
        $expectedName = 'foo';
        $expectedOptions = array('bar' => 'baz');

        $factory = $this->getMock('Symfony\Component\Form\FormFactoryInterface');
        $factory->expects($this->once())
                ->method('createBuilderForProperty')
                ->with($this->equalTo('stdClass'), $this->equalTo($expectedName), $this->equalTo($expectedOptions))
                ->will($this->returnValue($this->getFormBuilder()));

        $this->builder = new FormBuilder($this->dispatcher, 'stdClass');
        $this->builder->setFormFactory($factory);
        $this->builder->add($expectedName, null, $expectedOptions);
        $builder = $this->builder->get($expectedName);

        $this->assertNotSame($builder, $this->builder);
    }

    private function getFormBuilder()
    {
        return $this->getMockBuilder('Symfony\Component\Form\FormBuilder')
            ->disableOriginalConstructor()
            ->getMock();
    }
}