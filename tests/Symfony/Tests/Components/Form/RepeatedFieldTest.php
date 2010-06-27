<?php

namespace Symfony\Tests\Components\Form;

require_once __DIR__ . '/Fixtures/TestField.php';

use Symfony\Components\Form\RepeatedField;
use Symfony\Tests\Components\Form\Fixtures\TestField;

class RepeatedFieldTest extends \PHPUnit_Framework_TestCase
{
    protected $field;

    public function setUp()
    {
        $this->field = new RepeatedField(new TestField('name'));
    }

    public function testSetData()
    {
        $this->field->setData('foobar');

        $this->assertEquals('foobar', $this->field['first']->getData());
        $this->assertEquals('foobar', $this->field['second']->getData());
    }

    public function testBindUnequal()
    {
        $input = array('first' => 'foo', 'second' => 'bar');

        $this->field->bind($input);

        $this->assertEquals('foo', $this->field['first']->getDisplayedData());
        $this->assertEquals('bar', $this->field['second']->getDisplayedData());
        $this->assertFalse($this->field->isFirstEqualToSecond());
        $this->assertEquals($input, $this->field->getDisplayedData());
        $this->assertEquals(null, $this->field->getData());
    }

    public function testBindEqual()
    {
        $input = array('first' => 'foo', 'second' => 'foo');

        $this->field->bind($input);

        $this->assertEquals('foo', $this->field['first']->getDisplayedData());
        $this->assertEquals('foo', $this->field['second']->getDisplayedData());
        $this->assertTrue($this->field->isFirstEqualToSecond());
        $this->assertEquals($input, $this->field->getDisplayedData());
        $this->assertEquals('foo', $this->field->getData());
    }
}