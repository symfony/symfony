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

use Symfony\Component\Form\CollectionField;
use Symfony\Component\Form\Form;
use Symfony\Tests\Component\Form\Fixtures\TestField;

class CollectionFieldTest extends \PHPUnit_Framework_TestCase
{
    public function testContainsNoFieldsByDefault()
    {
        $field = new CollectionField('emails', array(
            'prototype' => new TestField(),
        ));
        $this->assertEquals(0, count($field));
    }

    public function testSetDataAdjustsSize()
    {
        $field = new CollectionField('emails', array(
            'prototype' => new TestField(),
        ));
        $field->setData(array('foo@foo.com', 'foo@bar.com'));

        $this->assertTrue($field[0] instanceof TestField);
        $this->assertTrue($field[1] instanceof TestField);
        $this->assertEquals(2, count($field));
        $this->assertEquals('foo@foo.com', $field[0]->getData());
        $this->assertEquals('foo@bar.com', $field[1]->getData());

        $field->setData(array('foo@baz.com'));
        $this->assertTrue($field[0] instanceof TestField);
        $this->assertFalse(isset($field[1]));
        $this->assertEquals(1, count($field));
        $this->assertEquals('foo@baz.com', $field[0]->getData());
    }

    public function testSetDataAdjustsSizeIfModifiable()
    {
        $field = new CollectionField('emails', array(
            'prototype' => new TestField(),
            'modifiable' => true,
        ));
        $field->setData(array('foo@foo.com', 'foo@bar.com'));

        $this->assertTrue($field[0] instanceof TestField);
        $this->assertTrue($field[1] instanceof TestField);
        $this->assertTrue($field['$$key$$'] instanceof TestField);
        $this->assertEquals(3, count($field));

        $field->setData(array('foo@baz.com'));
        $this->assertTrue($field[0] instanceof TestField);
        $this->assertFalse(isset($field[1]));
        $this->assertTrue($field['$$key$$'] instanceof TestField);
        $this->assertEquals(2, count($field));
    }

    public function testThrowsExceptionIfObjectIsNotTraversable()
    {
        $field = new CollectionField('emails', array(
            'prototype' => new TestField(),
        ));
        $this->setExpectedException('Symfony\Component\Form\Exception\UnexpectedTypeException');
        $field->setData(new \stdClass());
    }

    public function testModifiableCollectionsContainExtraField()
    {
        $field = new CollectionField('emails', array(
            'prototype' => new TestField(),
            'modifiable' => true,
        ));
        $field->setData(array('foo@bar.com'));

        $this->assertTrue($field['0'] instanceof TestField);
        $this->assertTrue($field['$$key$$'] instanceof TestField);
        $this->assertEquals(2, count($field));
    }

    public function testNotResizedIfSubmittedWithMissingData()
    {
        $field = new CollectionField('emails', array(
            'prototype' => new TestField(),
        ));
        $field->setData(array('foo@foo.com', 'bar@bar.com'));
        $field->submit(array('foo@bar.com'));

        $this->assertTrue($field->has('0'));
        $this->assertTrue($field->has('1'));
        $this->assertEquals('foo@bar.com', $field[0]->getData());
        $this->assertEquals(null, $field[1]->getData());
    }

    public function testResizedIfSubmittedWithMissingDataAndModifiable()
    {
        $field = new CollectionField('emails', array(
            'prototype' => new TestField(),
            'modifiable' => true,
        ));
        $field->setData(array('foo@foo.com', 'bar@bar.com'));
        $field->submit(array('foo@bar.com'));

        $this->assertTrue($field->has('0'));
        $this->assertFalse($field->has('1'));
        $this->assertEquals('foo@bar.com', $field[0]->getData());
    }

    public function testNotResizedIfSubmittedWithExtraData()
    {
        $field = new CollectionField('emails', array(
            'prototype' => new TestField(),
        ));
        $field->setData(array('foo@bar.com'));
        $field->submit(array('foo@foo.com', 'bar@bar.com'));

        $this->assertTrue($field->has('0'));
        $this->assertFalse($field->has('1'));
        $this->assertEquals('foo@foo.com', $field[0]->getData());
    }

    public function testResizedUpIfSubmittedWithExtraDataAndModifiable()
    {
        $field = new CollectionField('emails', array(
            'prototype' => new TestField(),
            'modifiable' => true,
        ));
        $field->setData(array('foo@bar.com'));
        $field->submit(array('foo@foo.com', 'bar@bar.com'));

        $this->assertTrue($field->has('0'));
        $this->assertTrue($field->has('1'));
        $this->assertEquals('foo@foo.com', $field[0]->getData());
        $this->assertEquals('bar@bar.com', $field[1]->getData());
        $this->assertEquals(array('foo@foo.com', 'bar@bar.com'), $field->getData());
    }

    public function testResizedDownIfSubmittedWithLessDataAndModifiable()
    {
        $field = new CollectionField('emails', array(
            'prototype' => new TestField(),
            'modifiable' => true,
        ));
        $field->setData(array('foo@bar.com', 'bar@bar.com'));
        $field->submit(array('foo@foo.com'));

        $this->assertTrue($field->has('0'));
        $this->assertFalse($field->has('1'));
        $this->assertEquals('foo@foo.com', $field[0]->getData());
        $this->assertEquals(array('foo@foo.com'), $field->getData());
    }
}
