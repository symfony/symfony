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

require_once __DIR__.'/TestCase.php';

use Symfony\Component\Form\RepeatedField;
use Symfony\Component\Form\Field;

class RepeatedFieldTest extends TestCase
{
    protected $field;

    protected function setUp()
    {
        parent::setUp();

        $this->field = $this->factory->create('repeated', 'name', array(
            'prototype' => $this->factory->create('field', 'foo'),
        ));
        $this->field->setData(null);
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

        $this->field->bind($input);

        $this->assertEquals('foo', $this->field['first']->getTransformedData());
        $this->assertEquals('bar', $this->field['second']->getTransformedData());
        $this->assertFalse($this->field->isTransformationSuccessful());
        $this->assertEquals($input, $this->field->getTransformedData());
        $this->assertEquals(null, $this->field->getData());
    }

    public function testSubmitEqual()
    {
        $input = array('first' => 'foo', 'second' => 'foo');

        $this->field->bind($input);

        $this->assertEquals('foo', $this->field['first']->getTransformedData());
        $this->assertEquals('foo', $this->field['second']->getTransformedData());
        $this->assertTrue($this->field->isTransformationSuccessful());
        $this->assertEquals($input, $this->field->getTransformedData());
        $this->assertEquals('foo', $this->field->getData());
    }
}