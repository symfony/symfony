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

use Symfony\Component\Form\CollectionForm;
use Symfony\Component\Form\Form;

class CollectionFormTest extends TestCase
{
    public function testContainsOnlyCsrfTokenByDefault()
    {
        $form = $this->factory->create('collection', 'emails', array(
            'type' => 'field',
            'csrf_field_name' => 'abc',
        ));

        $this->assertTrue($form->has('abc'));
        $this->assertEquals(1, count($form));
    }

    public function testSetDataAdjustsSize()
    {
        $form = $this->factory->create('collection', 'emails', array(
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

    public function testSetDataAdjustsSizeIfModifiable()
    {
        $form = $this->factory->create('collection', 'emails', array(
            'type' => 'field',
            'modifiable' => true,
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
        $form = $this->factory->create('collection', 'emails', array(
            'type' => 'field',
        ));
        $this->setExpectedException('Symfony\Component\Form\Exception\UnexpectedTypeException');
        $form->setData(new \stdClass());
    }

    public function testModifiableCollectionsContainExtraForm()
    {
        $form = $this->factory->create('collection', 'emails', array(
            'type' => 'field',
            'modifiable' => true,
            'prototype' => true,
        ));
        $form->setData(array('foo@bar.com'));

        $this->assertTrue($form['0'] instanceof Form);
        $this->assertTrue($form['$$name$$'] instanceof Form);
        $this->assertEquals(2, count($form));
    }

    public function testNotResizedIfBoundWithMissingData()
    {
        $form = $this->factory->create('collection', 'emails', array(
            'type' => 'field',
        ));
        $form->setData(array('foo@foo.com', 'bar@bar.com'));
        $form->bind(array('foo@bar.com'));

        $this->assertTrue($form->has('0'));
        $this->assertTrue($form->has('1'));
        $this->assertEquals('foo@bar.com', $form[0]->getData());
        $this->assertEquals(null, $form[1]->getData());
    }

    public function testResizedIfBoundWithMissingDataAndModifiable()
    {
        $form = $this->factory->create('collection', 'emails', array(
            'type' => 'field',
            'modifiable' => true,
        ));
        $form->setData(array('foo@foo.com', 'bar@bar.com'));
        $form->bind(array('foo@bar.com'));

        $this->assertTrue($form->has('0'));
        $this->assertFalse($form->has('1'));
        $this->assertEquals('foo@bar.com', $form[0]->getData());
    }

    public function testNotResizedIfBoundWithExtraData()
    {
        $form = $this->factory->create('collection', 'emails', array(
            'type' => 'field',
        ));
        $form->setData(array('foo@bar.com'));
        $form->bind(array('foo@foo.com', 'bar@bar.com'));

        $this->assertTrue($form->has('0'));
        $this->assertFalse($form->has('1'));
        $this->assertEquals('foo@foo.com', $form[0]->getData());
    }

    public function testResizedUpIfBoundWithExtraDataAndModifiable()
    {
        $form = $this->factory->create('collection', 'emails', array(
            'type' => 'field',
            'modifiable' => true,
        ));
        $form->setData(array('foo@bar.com'));
        $form->bind(array('foo@foo.com', 'bar@bar.com'));

        $this->assertTrue($form->has('0'));
        $this->assertTrue($form->has('1'));
        $this->assertEquals('foo@foo.com', $form[0]->getData());
        $this->assertEquals('bar@bar.com', $form[1]->getData());
        $this->assertEquals(array('foo@foo.com', 'bar@bar.com'), $form->getData());
    }

    public function testModifableButNoPrototype()
    {
        $form = $this->factory->create('collection', 'emails', array(
            'type' => 'field',
            'modifiable' => true,
            'prototype' => false,
        ));

        $this->assertFalse($form->has('$$name$$'));
    }

    public function testResizedDownIfBoundWithLessDataAndModifiable()
    {
        $form = $this->factory->create('collection', 'emails', array(
            'type' => 'field',
            'modifiable' => true,
        ));
        $form->setData(array('foo@bar.com', 'bar@bar.com'));
        $form->bind(array('foo@foo.com'));

        $this->assertTrue($form->has('0'));
        $this->assertFalse($form->has('1'));
        $this->assertEquals('foo@foo.com', $form[0]->getData());
        $this->assertEquals(array('foo@foo.com'), $form->getData());
    }
}
