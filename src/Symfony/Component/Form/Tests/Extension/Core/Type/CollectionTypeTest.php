<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Form\Tests\Extension\Core\Type;

use Symfony\Component\Form\Tests\Fixtures\Author;
use Symfony\Component\Form\Tests\Fixtures\AuthorType;

class CollectionTypeTest extends BaseTypeTest
{
    const TESTED_TYPE = 'Symfony\Component\Form\Extension\Core\Type\CollectionType';

    public function testContainsNoChildByDefault()
    {
        $form = $this->factory->create(static::TESTED_TYPE, null, array(
            'entry_type' => TextTypeTest::TESTED_TYPE,
        ));

        $this->assertCount(0, $form);
    }

    public function testSetDataAdjustsSize()
    {
        $form = $this->factory->create(static::TESTED_TYPE, null, array(
            'entry_type' => TextTypeTest::TESTED_TYPE,
            'entry_options' => array(
                'attr' => array('maxlength' => 20),
            ),
        ));
        $form->setData(array('foo@foo.com', 'foo@bar.com'));

        $this->assertInstanceOf('Symfony\Component\Form\Form', $form[0]);
        $this->assertInstanceOf('Symfony\Component\Form\Form', $form[1]);
        $this->assertCount(2, $form);
        $this->assertEquals('foo@foo.com', $form[0]->getData());
        $this->assertEquals('foo@bar.com', $form[1]->getData());
        $formAttrs0 = $form[0]->getConfig()->getOption('attr');
        $formAttrs1 = $form[1]->getConfig()->getOption('attr');
        $this->assertEquals(20, $formAttrs0['maxlength']);
        $this->assertEquals(20, $formAttrs1['maxlength']);

        $form->setData(array('foo@baz.com'));
        $this->assertInstanceOf('Symfony\Component\Form\Form', $form[0]);
        $this->assertArrayNotHasKey(1, $form);
        $this->assertCount(1, $form);
        $this->assertEquals('foo@baz.com', $form[0]->getData());
        $formAttrs0 = $form[0]->getConfig()->getOption('attr');
        $this->assertEquals(20, $formAttrs0['maxlength']);
    }

    public function testThrowsExceptionIfObjectIsNotTraversable()
    {
        $form = $this->factory->create(static::TESTED_TYPE, null, array(
            'entry_type' => TextTypeTest::TESTED_TYPE,
        ));
        $this->{method_exists($this, $_ = 'expectException') ? $_ : 'setExpectedException'}('Symfony\Component\Form\Exception\UnexpectedTypeException');
        $form->setData(new \stdClass());
    }

    public function testNotResizedIfSubmittedWithMissingData()
    {
        $form = $this->factory->create(static::TESTED_TYPE, null, array(
            'entry_type' => TextTypeTest::TESTED_TYPE,
        ));
        $form->setData(array('foo@foo.com', 'bar@bar.com'));
        $form->submit(array('foo@bar.com'));

        $this->assertTrue($form->has('0'));
        $this->assertTrue($form->has('1'));
        $this->assertEquals('foo@bar.com', $form[0]->getData());
        $this->assertEquals('', $form[1]->getData());
    }

    public function testResizedDownIfSubmittedWithMissingDataAndAllowDelete()
    {
        $form = $this->factory->create(static::TESTED_TYPE, null, array(
            'entry_type' => TextTypeTest::TESTED_TYPE,
            'allow_delete' => true,
        ));
        $form->setData(array('foo@foo.com', 'bar@bar.com'));
        $form->submit(array('foo@foo.com'));

        $this->assertTrue($form->has('0'));
        $this->assertFalse($form->has('1'));
        $this->assertEquals('foo@foo.com', $form[0]->getData());
        $this->assertEquals(array('foo@foo.com'), $form->getData());
    }

    public function testResizedDownIfSubmittedWithEmptyDataAndDeleteEmpty()
    {
        $form = $this->factory->create(static::TESTED_TYPE, null, array(
            'entry_type' => TextTypeTest::TESTED_TYPE,
            'allow_delete' => true,
            'delete_empty' => true,
        ));

        $form->setData(array('foo@foo.com', 'bar@bar.com'));
        $form->submit(array('foo@foo.com', ''));

        $this->assertTrue($form->has('0'));
        $this->assertFalse($form->has('1'));
        $this->assertEquals('foo@foo.com', $form[0]->getData());
        $this->assertEquals(array('foo@foo.com'), $form->getData());
    }

    public function testResizedDownWithDeleteEmptyCallable()
    {
        $form = $this->factory->create(static::TESTED_TYPE, null, array(
            'entry_type' => AuthorType::class,
            'allow_delete' => true,
            'delete_empty' => function (Author $obj = null) {
                return null === $obj || empty($obj->firstName);
            },
        ));

        $form->setData(array(new Author('Bob'), new Author('Alice')));
        $form->submit(array(array('firstName' => 'Bob'), array('firstName' => '')));

        $this->assertTrue($form->has('0'));
        $this->assertFalse($form->has('1'));
        $this->assertEquals(new Author('Bob'), $form[0]->getData());
        $this->assertEquals(array(new Author('Bob')), $form->getData());
    }

    public function testResizedDownIfSubmittedWithCompoundEmptyDataDeleteEmptyAndNoDataClass()
    {
        $form = $this->factory->create(static::TESTED_TYPE, null, array(
            'entry_type' => AuthorType::class,
            // If the field is not required, no new Author will be created if the
            // form is completely empty
            'entry_options' => array('data_class' => null),
            'allow_add' => true,
            'allow_delete' => true,
            'delete_empty' => function ($author) {
                return empty($author['firstName']);
            },
        ));
        $form->setData(array(array('firstName' => 'first', 'lastName' => 'last')));
        $form->submit(array(
            array('firstName' => 's_first', 'lastName' => 's_last'),
            array('firstName' => '', 'lastName' => ''),
        ));
        $this->assertTrue($form->has('0'));
        $this->assertFalse($form->has('1'));
        $this->assertEquals(array('firstName' => 's_first', 'lastName' => 's_last'), $form[0]->getData());
        $this->assertEquals(array(array('firstName' => 's_first', 'lastName' => 's_last')), $form->getData());
    }

    public function testDontAddEmptyDataIfDeleteEmpty()
    {
        $form = $this->factory->create(static::TESTED_TYPE, null, array(
            'entry_type' => TextTypeTest::TESTED_TYPE,
            'allow_add' => true,
            'delete_empty' => true,
        ));

        $form->setData(array('foo@foo.com'));
        $form->submit(array('foo@foo.com', ''));

        $this->assertTrue($form->has('0'));
        $this->assertFalse($form->has('1'));
        $this->assertEquals('foo@foo.com', $form[0]->getData());
        $this->assertEquals(array('foo@foo.com'), $form->getData());
    }

    public function testNoDeleteEmptyIfDeleteNotAllowed()
    {
        $form = $this->factory->create(static::TESTED_TYPE, null, array(
            'entry_type' => TextTypeTest::TESTED_TYPE,
            'allow_delete' => false,
            'delete_empty' => true,
        ));

        $form->setData(array('foo@foo.com'));
        $form->submit(array(''));

        $this->assertTrue($form->has('0'));
        $this->assertEquals('', $form[0]->getData());
    }

    public function testResizedDownIfSubmittedWithCompoundEmptyDataAndDeleteEmpty()
    {
        $form = $this->factory->create(static::TESTED_TYPE, null, array(
            'entry_type' => 'Symfony\Component\Form\Tests\Fixtures\AuthorType',
            // If the field is not required, no new Author will be created if the
            // form is completely empty
            'entry_options' => array('required' => false),
            'allow_add' => true,
            'delete_empty' => true,
        ));

        $form->setData(array(new Author('first', 'last')));
        $form->submit(array(
            array('firstName' => 's_first', 'lastName' => 's_last'),
            array('firstName' => '', 'lastName' => ''),
        ));

        $this->assertTrue($form->has('0'));
        $this->assertFalse($form->has('1'));
        $this->assertEquals(new Author('s_first', 's_last'), $form[0]->getData());
        $this->assertEquals(array(new Author('s_first', 's_last')), $form->getData());
    }

    public function testNotResizedIfSubmittedWithExtraData()
    {
        $form = $this->factory->create(static::TESTED_TYPE, null, array(
            'entry_type' => TextTypeTest::TESTED_TYPE,
        ));
        $form->setData(array('foo@bar.com'));
        $form->submit(array('foo@foo.com', 'bar@bar.com'));

        $this->assertTrue($form->has('0'));
        $this->assertFalse($form->has('1'));
        $this->assertEquals('foo@foo.com', $form[0]->getData());
    }

    public function testResizedUpIfSubmittedWithExtraDataAndAllowAdd()
    {
        $form = $this->factory->create(static::TESTED_TYPE, null, array(
            'entry_type' => TextTypeTest::TESTED_TYPE,
            'allow_add' => true,
        ));
        $form->setData(array('foo@bar.com'));
        $form->submit(array('foo@bar.com', 'bar@bar.com'));

        $this->assertTrue($form->has('0'));
        $this->assertTrue($form->has('1'));
        $this->assertEquals('foo@bar.com', $form[0]->getData());
        $this->assertEquals('bar@bar.com', $form[1]->getData());
        $this->assertEquals(array('foo@bar.com', 'bar@bar.com'), $form->getData());
    }

    public function testAllowAddButNoPrototype()
    {
        $form = $this->factory->create(static::TESTED_TYPE, null, array(
            'entry_type' => FormTypeTest::TESTED_TYPE,
            'allow_add' => true,
            'prototype' => false,
        ));

        $this->assertFalse($form->has('__name__'));
    }

    public function testPrototypeMultipartPropagation()
    {
        $form = $this->factory
            ->create(static::TESTED_TYPE, null, array(
                'entry_type' => FileTypeTest::TESTED_TYPE,
                'allow_add' => true,
                'prototype' => true,
            ))
        ;

        $this->assertTrue($form->createView()->vars['multipart']);
    }

    public function testGetDataDoesNotContainsPrototypeNameBeforeDataAreSet()
    {
        $form = $this->factory->create(static::TESTED_TYPE, array(), array(
            'entry_type' => FileTypeTest::TESTED_TYPE,
            'prototype' => true,
            'allow_add' => true,
        ));

        $data = $form->getData();
        $this->assertArrayNotHasKey('__name__', $data);
    }

    public function testGetDataDoesNotContainsPrototypeNameAfterDataAreSet()
    {
        $form = $this->factory->create(static::TESTED_TYPE, array(), array(
            'entry_type' => FileTypeTest::TESTED_TYPE,
            'allow_add' => true,
            'prototype' => true,
        ));

        $form->setData(array('foobar.png'));
        $data = $form->getData();
        $this->assertArrayNotHasKey('__name__', $data);
    }

    public function testPrototypeNameOption()
    {
        $form = $this->factory->create(static::TESTED_TYPE, null, array(
            'entry_type' => FormTypeTest::TESTED_TYPE,
            'prototype' => true,
            'allow_add' => true,
        ));

        $this->assertSame('__name__', $form->getConfig()->getAttribute('prototype')->getName(), '__name__ is the default');

        $form = $this->factory->create(static::TESTED_TYPE, null, array(
            'entry_type' => FormTypeTest::TESTED_TYPE,
            'prototype' => true,
            'allow_add' => true,
            'prototype_name' => '__test__',
        ));

        $this->assertSame('__test__', $form->getConfig()->getAttribute('prototype')->getName());
    }

    public function testPrototypeDefaultLabel()
    {
        $form = $this->factory->create(static::TESTED_TYPE, array(), array(
            'entry_type' => FileTypeTest::TESTED_TYPE,
            'allow_add' => true,
            'prototype' => true,
            'prototype_name' => '__test__',
        ));

        $this->assertSame('__test__label__', $form->createView()->vars['prototype']->vars['label']);
    }

    public function testPrototypeData()
    {
        $form = $this->factory->create(static::TESTED_TYPE, array(), array(
            'allow_add' => true,
            'prototype' => true,
            'prototype_data' => 'foo',
            'entry_type' => TextTypeTest::TESTED_TYPE,
            'entry_options' => array(
                'data' => 'bar',
                'label' => false,
            ),
        ));

        $this->assertSame('foo', $form->createView()->vars['prototype']->vars['value']);
        $this->assertFalse($form->createView()->vars['prototype']->vars['label']);
    }

    public function testPrototypeDefaultRequired()
    {
        $form = $this->factory->create(static::TESTED_TYPE, array(), array(
            'entry_type' => FileTypeTest::TESTED_TYPE,
            'allow_add' => true,
            'prototype' => true,
            'prototype_name' => '__test__',
        ));

        $this->assertTrue($form->createView()->vars['prototype']->vars['required']);
    }

    public function testPrototypeSetNotRequired()
    {
        $form = $this->factory->create(static::TESTED_TYPE, array(), array(
            'entry_type' => FileTypeTest::TESTED_TYPE,
            'allow_add' => true,
            'prototype' => true,
            'prototype_name' => '__test__',
            'required' => false,
        ));

        $this->assertFalse($form->createView()->vars['required'], 'collection is not required');
        $this->assertFalse($form->createView()->vars['prototype']->vars['required'], '"prototype" should not be required');
    }

    public function testPrototypeSetNotRequiredIfParentNotRequired()
    {
        $child = $this->factory->create(static::TESTED_TYPE, array(), array(
            'entry_type' => FileTypeTest::TESTED_TYPE,
            'allow_add' => true,
            'prototype' => true,
            'prototype_name' => '__test__',
        ));

        $parent = $this->factory->create(FormTypeTest::TESTED_TYPE, array(), array(
            'required' => false,
        ));

        $child->setParent($parent);
        $this->assertFalse($parent->createView()->vars['required'], 'Parent is not required');
        $this->assertFalse($child->createView()->vars['required'], 'Child is not required');
        $this->assertFalse($child->createView()->vars['prototype']->vars['required'], '"Prototype" should not be required');
    }

    public function testPrototypeNotOverrideRequiredByEntryOptionsInFavorOfParent()
    {
        $child = $this->factory->create(static::TESTED_TYPE, array(), array(
            'entry_type' => FileTypeTest::TESTED_TYPE,
            'allow_add' => true,
            'prototype' => true,
            'prototype_name' => '__test__',
            'entry_options' => array(
                'required' => true,
            ),
        ));

        $parent = $this->factory->create(FormTypeTest::TESTED_TYPE, array(), array(
            'required' => false,
        ));

        $child->setParent($parent);

        $this->assertFalse($parent->createView()->vars['required'], 'Parent is not required');
        $this->assertFalse($child->createView()->vars['required'], 'Child is not required');
        $this->assertFalse($child->createView()->vars['prototype']->vars['required'], '"Prototype" should not be required');
    }

    public function testSubmitNull($expected = null, $norm = null, $view = null)
    {
        parent::testSubmitNull(array(), array(), array());
    }

    public function testSubmitNullUsesDefaultEmptyData($emptyData = array(), $expectedData = array())
    {
        // resize form listener always set an empty array
        parent::testSubmitNullUsesDefaultEmptyData($emptyData, $expectedData);
    }
}
