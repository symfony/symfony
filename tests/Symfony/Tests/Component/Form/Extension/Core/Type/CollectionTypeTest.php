<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Tests\Component\Form\Extension\Core\Type;

use Symfony\Component\Form\CollectionForm;
use Symfony\Component\Form\Form;

class CollectionFormTest extends TypeTestCase
{
    public function testContainsNoFieldByDefault()
    {
        $form = $this->factory->create('collection', null, array(
            'type' => 'field',
        ));

        $this->assertEquals(0, count($form));
    }

    public function testSetDataAdjustsSize()
    {
        $form = $this->factory->create('collection', null, array(
            'type' => 'field',
        ));
        $form->setData(array('foo@foo.com', 'foo@bar.com'));

        $this->assertTrue($form[0] instanceof Form);
        $this->assertTrue($form[1] instanceof Form);
        $this->assertEquals(2, count($form));
        $this->assertEquals('foo@foo.com', $form[0]->getData());
        $this->assertEquals('foo@bar.com', $form[1]->getData());

        $form->setData(array('foo@baz.com'));
        $this->assertTrue($form[0] instanceof Form);
        $this->assertFalse(isset($form[1]));
        $this->assertEquals(1, count($form));
        $this->assertEquals('foo@baz.com', $form[0]->getData());
    }

    public function testSetDataAddsPrototypeIfAllowAdd()
    {
        $form = $this->factory->create('collection', null, array(
            'type' => 'field',
            'allow_add' => true,
            'prototype' => true,
        ));

        $form->setData(array('foo@foo.com', 'foo@bar.com'));
        $this->assertTrue($form[0] instanceof Form);
        $this->assertTrue($form[1] instanceof Form);
        $this->assertTrue($form['$$name$$'] instanceof Form);
        $this->assertEquals(3, count($form));

        $form->setData(array('foo@baz.com'));
        $this->assertTrue($form[0] instanceof Form);
        $this->assertFalse(isset($form[1]));
        $this->assertTrue($form['$$name$$'] instanceof Form);
        $this->assertEquals(2, count($form));
    }

    public function testThrowsExceptionIfObjectIsNotTraversable()
    {
        $form = $this->factory->create('collection', null, array(
            'type' => 'field',
        ));
        $this->setExpectedException('Symfony\Component\Form\Exception\UnexpectedTypeException');
        $form->setData(new \stdClass());
    }

    public function testNotResizedIfBoundWithMissingData()
    {
        $form = $this->factory->create('collection', null, array(
            'type' => 'field',
        ));
        $form->setData(array('foo@foo.com', 'bar@bar.com'));
        $form->bind(array('foo@bar.com'));

        $this->assertTrue($form->has('0'));
        $this->assertTrue($form->has('1'));
        $this->assertEquals('foo@bar.com', $form[0]->getData());
        $this->assertEquals(null, $form[1]->getData());
    }

    public function testResizedDownIfBoundWithMissingDataAndAllowDelete()
    {
        $form = $this->factory->create('collection', null, array(
            'type' => 'field',
            'allow_delete' => true,
        ));
        $form->setData(array('foo@foo.com', 'bar@bar.com'));
        $form->bind(array('foo@bar.com'));

        $this->assertTrue($form->has('0'));
        $this->assertFalse($form->has('1'));
        $this->assertEquals('foo@bar.com', $form[0]->getData());
        $this->assertEquals(array('foo@bar.com'), $form->getData());
    }

    public function testNotResizedIfBoundWithExtraData()
    {
        $form = $this->factory->create('collection', null, array(
            'type' => 'field',
        ));
        $form->setData(array('foo@bar.com'));
        $form->bind(array('foo@foo.com', 'bar@bar.com'));

        $this->assertTrue($form->has('0'));
        $this->assertFalse($form->has('1'));
        $this->assertEquals('foo@foo.com', $form[0]->getData());
    }

    public function testResizedUpIfBoundWithExtraDataAndAllowAdd()
    {
        $form = $this->factory->create('collection', null, array(
            'type' => 'field',
            'allow_add' => true,
        ));
        $form->setData(array('foo@bar.com'));
        $form->bind(array('foo@foo.com', 'bar@bar.com'));

        $this->assertTrue($form->has('0'));
        $this->assertTrue($form->has('1'));
        $this->assertEquals('foo@foo.com', $form[0]->getData());
        $this->assertEquals('bar@bar.com', $form[1]->getData());
        $this->assertEquals(array('foo@foo.com', 'bar@bar.com'), $form->getData());
    }

    public function testAllowAddButNoPrototype()
    {
        $form = $this->factory->create('collection', null, array(
            'type' => 'field',
            'allow_add' => true,
            'prototype' => false,
        ));

        $this->assertFalse($form->has('$$name$$'));
    }

    public function testSetTypeOptions()
    {
        $form = $this->factory->create('collection', null, array(
            'type' => 'field',
            'type_options' => array(
                'required' => false,
                'max_length' => 20
            ),
        ));
        $form->setData(array('foo@foo.com', 'foo@bar.com'));

        $this->assertFalse($form[0]->isRequired());
        $this->assertFalse($form[1]->isRequired());
        $this->assertEquals(20, $form[0]->getAttribute('max_length'));
        $this->assertEquals(20, $form[1]->getAttribute('max_length'));

        $form->bind(array('foo@bar.com', 'bar@foo.com'));

        $this->assertFalse($form[0]->isRequired());
        $this->assertFalse($form[1]->isRequired());
        $this->assertEquals(20, $form[0]->getAttribute('max_length'));
        $this->assertEquals(20, $form[1]->getAttribute('max_length'));

        //Test with prototype and extra field
        $form = $this->factory->create('collection', null, array(
            'allow_add' => true,
            'prototype' => true,
            'type' => 'field',
            'type_options' => array(
                'required' => false,
                'max_length' => 20
            ),
        ));

        $form->setData(array('foo@foo.com'));

        $this->assertFalse($form[0]->isRequired());
        $this->assertEquals(20, $form[0]->getAttribute('max_length'));

        $form->bind(array('foo@bar.com', 'bar@foo.com'));

        $this->assertFalse($form[0]->isRequired());
        $this->assertFalse($form[1]->isRequired());
        $this->assertEquals(20, $form[0]->getAttribute('max_length'));
        $this->assertEquals(20, $form[1]->getAttribute('max_length'));

    }

    public function testSetTypeOptionsWithoutOptions()
    {
        $form = $this->factory->create('collection', null, array(
            'type' => 'field',
        ));
        $form->setData(array('foo@foo.com', 'foo@bar.com'));

        $this->assertTrue($form[0]->isRequired());
        $this->assertTrue($form[1]->isRequired());
        $this->assertNull($form[0]->getAttribute('max_length'));
        $this->assertNull($form[1]->getAttribute('max_length'));

        $form->bind(array('foo@bar.com', 'bar@foo.com'));

        $this->assertTrue($form[0]->isRequired());
        $this->assertTrue($form[1]->isRequired());
        $this->assertNull($form[0]->getAttribute('max_length'));
        $this->assertNull($form[1]->getAttribute('max_length'));

        //Test with prototype and extra field
        $form = $this->factory->create('collection', null, array(
            'allow_add' => true,
            'prototype' => true,
            'type' => 'field',
        ));

        $form->setData(array('foo@foo.com'));

        $this->assertTrue($form[0]->isRequired());
        $this->assertNull($form[0]->getAttribute('max_length'));

        $form->bind(array('foo@bar.com', 'bar@foo.com'));

        $this->assertTrue($form[0]->isRequired());
        $this->assertTrue($form[1]->isRequired());
        $this->assertNull($form[0]->getAttribute('max_length'));
        $this->assertNull($form[1]->getAttribute('max_length'));
    }
}
