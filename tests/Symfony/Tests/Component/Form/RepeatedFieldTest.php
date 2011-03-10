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

require_once __DIR__ . '/Fixtures/TestField.php';

use Symfony\Component\Form\RepeatedField;
use Symfony\Tests\Component\Form\Fixtures\TestField;

class RepeatedFieldTest extends \PHPUnit_Framework_TestCase
{
    protected $field;

    protected function setUp()
    {
        $this->field = new RepeatedField(new TestField('name'));
    }

    public function testSetData()
    {
        $this->field->setData('foobar');

        $this->assertEquals('foobar', $this->field['first']->getData());
        $this->assertEquals('foobar', $this->field['second']->getData());
    }

    public function testSubmitUnequal()
    {
        $input = array('first' => 'foo', 'second' => 'bar');

        $this->field->submit($input);

        $this->assertEquals('foo', $this->field['first']->getDisplayedData());
        $this->assertEquals('bar', $this->field['second']->getDisplayedData());
        $this->assertFalse($this->field->isFirstEqualToSecond());
        $this->assertEquals($input, $this->field->getDisplayedData());
        $this->assertEquals('foo', $this->field->getData());
    }

    public function testSubmitEqual()
    {
        $input = array('first' => 'foo', 'second' => 'foo');

        $this->field->submit($input);

        $this->assertEquals('foo', $this->field['first']->getDisplayedData());
        $this->assertEquals('foo', $this->field['second']->getDisplayedData());
        $this->assertTrue($this->field->isFirstEqualToSecond());
        $this->assertEquals($input, $this->field->getDisplayedData());
        $this->assertEquals('foo', $this->field->getData());
    }

    public function testGetDataReturnsSecondValueIfFirstIsEmpty()
    {
        $input = array('first' => '', 'second' => 'bar');

        $this->field->submit($input);

        $this->assertEquals('bar', $this->field->getData());
    }
}