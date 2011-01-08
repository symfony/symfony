<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
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

        $this->assertEquals('foobar', $this->field['name']->getData());
        $this->assertEquals('foobar', $this->field['name_repeat']->getData());
    }

    public function testBindUnequal()
    {
        $input = array('name' => 'foo', 'name_repeat' => 'bar');

        $this->field->bind($input);

        $this->assertEquals('foo', $this->field['name']->getDisplayedData());
        $this->assertEquals('bar', $this->field['name_repeat']->getDisplayedData());
        $this->assertFalse($this->field->isFirstEqualToSecond());
        $this->assertEquals($input, $this->field->getDisplayedData());
        $this->assertEquals(null, $this->field->getData());
    }

    public function testBindEqual()
    {
        $input = array('name' => 'foo', 'name_repeat' => 'foo');

        $this->field->bind($input);

        $this->assertEquals('foo', $this->field['name']->getDisplayedData());
        $this->assertEquals('foo', $this->field['name_repeat']->getDisplayedData());
        $this->assertTrue($this->field->isFirstEqualToSecond());
        $this->assertEquals($input, $this->field->getDisplayedData());
        $this->assertEquals('foo', $this->field->getData());
    }
}