<?php

namespace Symfony\Tests\Components\Form;

require_once __DIR__ . '/Fixtures/TestField.php';

use Symfony\Components\Form\CollectionField;
use Symfony\Tests\Components\Form\Fixtures\TestField;


class CollectionFieldTest extends \PHPUnit_Framework_TestCase
{
    public function testContainsNoFieldsByDefault()
    {
        $field = new CollectionField(new TestField('emails'));
        $this->assertEquals(0, count($field));
    }

    public function testSetDataAdjustsSize()
    {
        $field = new CollectionField(new TestField('emails'));
        $field->setData(array('foo@foo.com', 'foo@bar.com'));

        $this->assertTrue($field[0] instanceof TestField);
        $this->assertTrue($field[1] instanceof TestField);
        $this->assertEquals(2, count($field));
        $this->assertEquals('foo@foo.com', $field[0]->getData());
        $this->assertEquals('foo@bar.com', $field[1]->getData());
    }

    public function testThrowsExceptionIfObjectIsNotTraversable()
    {
        $field = new CollectionField(new TestField('emails'));
        $this->setExpectedException('Symfony\Components\Form\Exception\UnexpectedTypeException');
        $field->setData(new \stdClass());
    }

    public function testModifiableCollectionsContainExtraField()
    {
        $field = new CollectionField(new TestField('emails'), array(
            'modifiable' => true,
        ));
        $field->setData(array('foo@bar.com'));

        $this->assertTrue($field['0'] instanceof TestField);
        $this->assertTrue($field['$$key$$'] instanceof TestField);
        $this->assertEquals(2, count($field));
    }

    public function testNotResizedIfBoundWithMissingData()
    {
        $field = new CollectionField(new TestField('emails'));
        $field->setData(array('foo@foo.com', 'bar@bar.com'));
        $field->bind(array('foo@bar.com'));

        $this->assertTrue($field->has('0'));
        $this->assertTrue($field->has('1'));
        $this->assertEquals('foo@bar.com', $field[0]->getData());
        $this->assertEquals(null, $field[1]->getData());
    }

    public function testResizedIfBoundWithMissingDataAndModifiable()
    {
        $field = new CollectionField(new TestField('emails'), array(
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
        $field = new CollectionField(new TestField('emails'));
        $field->setData(array('foo@bar.com'));
        $field->bind(array('foo@foo.com', 'bar@bar.com'));

        $this->assertTrue($field->has('0'));
        $this->assertFalse($field->has('1'));
        $this->assertEquals('foo@foo.com', $field[0]->getData());
    }

    public function testResizedIfBoundWithExtraDataAndModifiable()
    {
        $field = new CollectionField(new TestField('emails'), array(
            'modifiable' => true,
        ));
        $field->setData(array('foo@bar.com'));
        $field->bind(array('foo@foo.com', 'bar@bar.com'));

        $this->assertTrue($field->has('0'));
        $this->assertTrue($field->has('1'));
        $this->assertEquals('foo@foo.com', $field[0]->getData());
        $this->assertEquals('bar@bar.com', $field[1]->getData());
    }
}
