<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Tests\Component\Form\Type;

require_once __DIR__.'/TestCase.php';

use Symfony\Component\Form\RepeatedField;
use Symfony\Component\Form\Field;

class RepeatedTypeTest extends TestCase
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

        $this->assertEquals('foo', $this->field['first']->getClientData());
        $this->assertEquals('bar', $this->field['second']->getClientData());
        $this->assertFalse($this->field->isSynchronized());
        $this->assertEquals($input, $this->field->getClientData());
        $this->assertEquals(null, $this->field->getData());
    }

    public function testSubmitEqual()
    {
        $input = array('first' => 'foo', 'second' => 'foo');

        $this->field->bind($input);

        $this->assertEquals('foo', $this->field['first']->getClientData());
        $this->assertEquals('foo', $this->field['second']->getClientData());
        $this->assertTrue($this->field->isSynchronized());
        $this->assertEquals($input, $this->field->getClientData());
        $this->assertEquals('foo', $this->field->getData());
    }
}