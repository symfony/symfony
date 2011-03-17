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

use Symfony\Component\Form\CollectionField;
use Symfony\Component\Form\Form;
use Symfony\Component\Form\Field;

class CollectionFieldTest extends TestCase
{
    public function testContainsOnlyCsrfTokenByDefault()
    {
        $field = $this->factory->getInstance('collection', 'emails', array(
            'prototype' => 'field',
            'csrf_field_name' => 'abc',
        ));

        $this->assertTrue($field->has('abc'));
        $this->assertEquals(1, count($field));
    }

    public function testSetDataAdjustsSize()
    {
        $field = $this->factory->getInstance('collection', 'emails', array(
            'prototype' => 'field',
        ));
        $field->setData(array('foo@foo.com', 'foo@bar.com'));

        $this->assertTrue($field[0] instanceof Field);
        $this->assertTrue($field[1] instanceof Field);
        $this->assertEquals(2, count($field));
        $this->assertEquals('foo@foo.com', $field[0]->getData());
        $this->assertEquals('foo@bar.com', $field[1]->getData());

        $field->setData(array('foo@baz.com'));
        $this->assertTrue($field[0] instanceof Field);
        $this->assertFalse(isset($field[1]));
        $this->assertEquals(1, count($field));
        $this->assertEquals('foo@baz.com', $field[0]->getData());
    }

    public function testSetDataAdjustsSizeIfModifiable()
    {
        $field = $this->factory->getInstance('collection', 'emails', array(
            'prototype' => 'field',
            'modifiable' => true,
        ));
        $field->setData(array('foo@foo.com', 'foo@bar.com'));

        $this->assertTrue($field[0] instanceof Field);
        $this->assertTrue($field[1] instanceof Field);
        $this->assertTrue($field['$$name$$'] instanceof Field);
        $this->assertEquals(3, count($field));

        $field->setData(array('foo@baz.com'));
        $this->assertTrue($field[0] instanceof Field);
        $this->assertFalse(isset($field[1]));
        $this->assertTrue($field['$$name$$'] instanceof Field);
        $this->assertEquals(2, count($field));
    }

    public function testThrowsExceptionIfObjectIsNotTraversable()
    {
        $field = $this->factory->getInstance('collection', 'emails', array(
            'prototype' => 'field',
        ));
        $this->setExpectedException('Symfony\Component\Form\Exception\UnexpectedTypeException');
        $field->setData(new \stdClass());
    }

    public function testModifiableCollectionsContainExtraField()
    {
        $field = $this->factory->getInstance('collection', 'emails', array(
            'prototype' => 'field',
            'modifiable' => true,
        ));
        $field->setData(array('foo@bar.com'));

        $this->assertTrue($field['0'] instanceof Field);
        $this->assertTrue($field['$$name$$'] instanceof Field);
        $this->assertEquals(2, count($field));
    }

    public function testNotResizedIfBoundWithMissingData()
    {
        $field = $this->factory->getInstance('collection', 'emails', array(
            'prototype' => 'field',
        ));
        $field->setData(array('foo@foo.com', 'bar@bar.com'));
        $field->bind(array('foo@bar.com'));

        $this->assertTrue($field->has('0'));
        $this->assertTrue($field->has('1'));
        $this->assertEquals('foo@bar.com', $field[0]->getData());
        $this->assertEquals(null, $field[1]->getData());
    }

    public function testResizedIfBoundWithMissingDataAndModifiable()
    {
        $field = $this->factory->getInstance('collection', 'emails', array(
            'prototype' => 'field',
            'modifiable' => true,
        ));
        $field->setData(array('foo@foo.com', 'bar@bar.com'));
        $field->bind(array('foo@bar.com'));

        $this->assertTrue($field->has('0'));
        $this->assertFalse($field->has('1'));
        $this->assertEquals('foo@bar.com', $field[0]->getData());
    }

    public function testNotResizedIfBoundWithExtraData()
    {
        $field = $this->factory->getInstance('collection', 'emails', array(
            'prototype' => 'field',
        ));
        $field->setData(array('foo@bar.com'));
        $field->bind(array('foo@foo.com', 'bar@bar.com'));

        $this->assertTrue($field->has('0'));
        $this->assertFalse($field->has('1'));
        $this->assertEquals('foo@foo.com', $field[0]->getData());
    }

    public function testResizedUpIfBoundWithExtraDataAndModifiable()
    {
        $field = $this->factory->getInstance('collection', 'emails', array(
            'prototype' => 'field',
            'modifiable' => true,
        ));
        $field->setData(array('foo@bar.com'));
        $field->bind(array('foo@foo.com', 'bar@bar.com'));

        $this->assertTrue($field->has('0'));
        $this->assertTrue($field->has('1'));
        $this->assertEquals('foo@foo.com', $field[0]->getData());
        $this->assertEquals('bar@bar.com', $field[1]->getData());
        $this->assertEquals(array('foo@foo.com', 'bar@bar.com'), $field->getData());
    }

    public function testResizedDownIfBoundWithLessDataAndModifiable()
    {
        $field = $this->factory->getInstance('collection', 'emails', array(
            'prototype' => 'field',
            'modifiable' => true,
        ));
        $field->setData(array('foo@bar.com', 'bar@bar.com'));
        $field->bind(array('foo@foo.com'));

        $this->assertTrue($field->has('0'));
        $this->assertFalse($field->has('1'));
        $this->assertEquals('foo@foo.com', $field[0]->getData());
        $this->assertEquals(array('foo@foo.com'), $field->getData());
    }
}
