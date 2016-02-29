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

class CollectionTypeTest extends \Symfony\Component\Form\Test\TypeTestCase
{
    public function testContainsNoChildByDefault()
    {
        $form = $this->factory->create('Symfony\Component\Form\Extension\Core\Type\CollectionType', null, array(
            'entry_type' => 'Symfony\Component\Form\Extension\Core\Type\TextType',
        ));

        $this->assertCount(0, $form);
    }

    public function testSetDataAdjustsSize()
    {
        $form = $this->factory->create('Symfony\Component\Form\Extension\Core\Type\CollectionType', null, array(
            'entry_type' => 'Symfony\Component\Form\Extension\Core\Type\TextType',
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
        $this->assertFalse(isset($form[1]));
        $this->assertCount(1, $form);
        $this->assertEquals('foo@baz.com', $form[0]->getData());
        $formAttrs0 = $form[0]->getConfig()->getOption('attr');
        $this->assertEquals(20, $formAttrs0['maxlength']);
    }

    public function testThrowsExceptionIfObjectIsNotTraversable()
    {
        $form = $this->factory->create('Symfony\Component\Form\Extension\Core\Type\CollectionType', null, array(
            'entry_type' => 'Symfony\Component\Form\Extension\Core\Type\TextType',
        ));
        $this->setExpectedException('Symfony\Component\Form\Exception\UnexpectedTypeException');
        $form->setData(new \stdClass());
    }

    public function testNotResizedIfSubmittedWithMissingData()
    {
        $form = $this->factory->create('Symfony\Component\Form\Extension\Core\Type\CollectionType', null, array(
            'entry_type' => 'Symfony\Component\Form\Extension\Core\Type\TextType',
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
        $form = $this->factory->create('Symfony\Component\Form\Extension\Core\Type\CollectionType', null, array(
            'entry_type' => 'Symfony\Component\Form\Extension\Core\Type\TextType',
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
        $form = $this->factory->create('Symfony\Component\Form\Extension\Core\Type\CollectionType', null, array(
            'entry_type' => 'Symfony\Component\Form\Extension\Core\Type\TextType',
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

    public function testDontAddEmptyDataIfDeleteEmpty()
    {
        $form = $this->factory->create('Symfony\Component\Form\Extension\Core\Type\CollectionType', null, array(
            'entry_type' => 'Symfony\Component\Form\Extension\Core\Type\TextType',
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
        $form = $this->factory->create('Symfony\Component\Form\Extension\Core\Type\CollectionType', null, array(
            'entry_type' => 'Symfony\Component\Form\Extension\Core\Type\TextType',
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
        $form = $this->factory->create('Symfony\Component\Form\Extension\Core\Type\CollectionType', null, array(
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
        $form = $this->factory->create('Symfony\Component\Form\Extension\Core\Type\CollectionType', null, array(
            'entry_type' => 'Symfony\Component\Form\Extension\Core\Type\TextType',
        ));
        $form->setData(array('foo@bar.com'));
        $form->submit(array('foo@foo.com', 'bar@bar.com'));

        $this->assertTrue($form->has('0'));
        $this->assertFalse($form->has('1'));
        $this->assertEquals('foo@foo.com', $form[0]->getData());
    }

    public function testResizedUpIfSubmittedWithExtraDataAndAllowAdd()
    {
        $form = $this->factory->create('Symfony\Component\Form\Extension\Core\Type\CollectionType', null, array(
            'entry_type' => 'Symfony\Component\Form\Extension\Core\Type\TextType',
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
        $form = $this->factory->create('Symfony\Component\Form\Extension\Core\Type\CollectionType', null, array(
            'entry_type' => 'Symfony\Component\Form\Extension\Core\Type\FormType',
            'allow_add' => true,
            'prototype' => false,
        ));

        $this->assertFalse($form->has('__name__'));
    }

    public function testPrototypeMultipartPropagation()
    {
        $form = $this->factory
            ->create('Symfony\Component\Form\Extension\Core\Type\CollectionType', null, array(
                'entry_type' => 'Symfony\Component\Form\Extension\Core\Type\FileType',
                'allow_add' => true,
                'prototype' => true,
            ))
        ;

        $this->assertTrue($form->createView()->vars['multipart']);
    }

    public function testGetDataDoesNotContainsPrototypeNameBeforeDataAreSet()
    {
        $form = $this->factory->create('Symfony\Component\Form\Extension\Core\Type\CollectionType', array(), array(
            'entry_type' => 'Symfony\Component\Form\Extension\Core\Type\FileType',
            'prototype' => true,
            'allow_add' => true,
        ));

        $data = $form->getData();
        $this->assertFalse(isset($data['__name__']));
    }

    public function testGetDataDoesNotContainsPrototypeNameAfterDataAreSet()
    {
        $form = $this->factory->create('Symfony\Component\Form\Extension\Core\Type\CollectionType', array(), array(
            'entry_type' => 'Symfony\Component\Form\Extension\Core\Type\FileType',
            'allow_add' => true,
            'prototype' => true,
        ));

        $form->setData(array('foobar.png'));
        $data = $form->getData();
        $this->assertFalse(isset($data['__name__']));
    }

    public function testPrototypeNameOption()
    {
        $form = $this->factory->create('Symfony\Component\Form\Extension\Core\Type\CollectionType', null, array(
            'entry_type' => 'Symfony\Component\Form\Extension\Core\Type\FormType',
            'prototype' => true,
            'allow_add' => true,
        ));

        $this->assertSame('__name__', $form->getConfig()->getAttribute('prototype')->getName(), '__name__ is the default');

        $form = $this->factory->create('Symfony\Component\Form\Extension\Core\Type\CollectionType', null, array(
            'entry_type' => 'Symfony\Component\Form\Extension\Core\Type\FormType',
            'prototype' => true,
            'allow_add' => true,
            'prototype_name' => '__test__',
        ));

        $this->assertSame('__test__', $form->getConfig()->getAttribute('prototype')->getName());
    }

    public function testPrototypeDefaultLabel()
    {
        $form = $this->factory->create('Symfony\Component\Form\Extension\Core\Type\CollectionType', array(), array(
            'entry_type' => 'Symfony\Component\Form\Extension\Core\Type\FileType',
            'allow_add' => true,
            'prototype' => true,
            'prototype_name' => '__test__',
        ));

        $this->assertSame('__test__label__', $form->createView()->vars['prototype']->vars['label']);
    }

    /**
     * @group legacy
     */
    public function testPrototypeData()
    {
        $form = $this->factory->create('Symfony\Component\Form\Extension\Core\Type\CollectionType', array(), array(
            'allow_add' => true,
            'prototype' => true,
            'prototype_data' => 'foo',
            'entry_type' => 'Symfony\Component\Form\Extension\Core\Type\TextType',
            'entry_options' => array(
                'data' => 'bar',
                'label' => false,
            ),
        ));

        $this->assertSame('foo', $form->createView()->vars['prototype']->vars['value']);
        $this->assertFalse($form->createView()->vars['prototype']->vars['label']);
    }

    public function testPrototypeOptions()
    {
        $form = $this->factory->create('Symfony\Component\Form\Extension\Core\Type\CollectionType', array(), array(
            'allow_add' => true,
            'prototype' => true,
            'entry_type' => 'Symfony\Component\Form\Extension\Core\Type\TextType',
            'entry_options' => array(
                'data' => 'foo',
                'label' => 'Item:',
                'attr' => array('class' => 'my&item&class'),
                'label_attr' => array('class' => 'my&item&label&class'),
            ),
            'prototype_options' => array(
                'data' => 'bar',
                'label' => false,
                'attr' => array('class' => 'my&prototype&class'),
            ),
        ));

        $this->assertSame('bar', $form->createView()->vars['prototype']->vars['value']);
        $this->assertFalse($form->createView()->vars['prototype']->vars['label']);
        $this->assertSame('my&prototype&class', $form->createView()->vars['prototype']->vars['attr']['class']);
        $this->assertSame('my&item&label&class', $form->createView()->vars['prototype']->vars['label_attr']['class']);
    }

    public function testPrototypeDefaultRequired()
    {
        $form = $this->factory->create('Symfony\Component\Form\Extension\Core\Type\CollectionType', array(), array(
            'entry_type' => 'Symfony\Component\Form\Extension\Core\Type\FileType',
            'allow_add' => true,
            'prototype' => true,
            'prototype_name' => '__test__',
        ));

        $this->assertTrue($form->createView()->vars['prototype']->vars['required']);
    }

    public function testPrototypeSetNotRequired()
    {
        $form = $this->factory->create('Symfony\Component\Form\Extension\Core\Type\CollectionType', array(), array(
            'entry_type' => 'Symfony\Component\Form\Extension\Core\Type\FileType',
            'allow_add' => true,
            'prototype' => true,
            'prototype_name' => '__test__',
            'required' => false,
        ));

        $this->assertFalse($form->createView()->vars['required'], 'collection is not required');
        $this->assertFalse($form->createView()->vars['prototype']->vars['required'], '"prototype" should not be required');
    }

    public function testEntryOptions()
    {
        $data = array(
            'foo',
            'bar',
        );

        $expectedAttr = array('class' => 'my&item&class', 'data-value' => 'my&value');

        $view = $this->factory->create('Symfony\Component\Form\Extension\Core\Type\CollectionType', $data, array(
            'allow_add' => true,
            'prototype' => true,
            'prototype_name' => '__test__',
            'entry_options' => array(
                'label' => 'Item Label:',
                'attr' => $expectedAttr,
            ),
        ))->createView();

        $this->assertSame('Item Label:', $view->children[0]->vars['label']);
        $this->assertSame('Item Label:', $view->children[1]->vars['label']);
        $this->assertSame($expectedAttr, $view->children[0]->vars['attr']);
        $this->assertSame($expectedAttr, $view->children[1]->vars['attr']);
        $this->assertSame('_collection_entry', $view->children[0]->vars['unique_block_prefix']);
        $this->assertSame('_collection_entry', $view->children[1]->vars['unique_block_prefix']);
    }

    public function testEntryOptionsAsCallable()
    {
        $data = array(
            'foo',
            'bar',
        );

        $view = $this->factory->create('Symfony\Component\Form\Extension\Core\Type\CollectionType', $data, array(
            'allow_add' => true,
            'prototype' => true,
            'prototype_name' => '__test__',
            'entry_options' => function ($entry) {
                return array(
                    'label' => ucfirst($entry).':',
                    'attr' => array('class' => $entry.'&class', 'data-upper' => strtoupper($entry)),
                );
            },
        ))->createView();

        $expectedAttr = array(
            array('class' => 'foo&class', 'data-upper' => 'FOO'),
            array('class' => 'bar&class', 'data-upper' => 'BAR'),
        );

        $this->assertSame('Foo:', $view->children[0]->vars['label']);
        $this->assertSame('Bar:', $view->children[1]->vars['label']);
        $this->assertSame($expectedAttr[0], $view->children[0]->vars['attr']);
        $this->assertSame($expectedAttr[1], $view->children[1]->vars['attr']);
        $this->assertSame('_collection_entry', $view->children[0]->vars['unique_block_prefix']);
        $this->assertSame('_collection_entry', $view->children[1]->vars['unique_block_prefix']);
    }

    public function setEntryOptions($entry)
    {
        return array(
            'label' => ucfirst($entry).':',
            'attr' => array('class' => $entry.'&class', 'data-upper' => strtoupper($entry)),
        );
    }

    public function testEntryOptionsAsCallableInArray()
    {
        $data = array(
            'foo',
            'bar',
        );

        $view = $this->factory->create('Symfony\Component\Form\Extension\Core\Type\CollectionType', $data, array(
            'allow_add' => true,
            'prototype' => true,
            'prototype_name' => '__test__',
            'entry_options' => array($this, 'setEntryOptions'),
        ))->createView();

        $expectedAttr = array(
            array('class' => 'foo&class', 'data-upper' => 'FOO'),
            array('class' => 'bar&class', 'data-upper' => 'BAR'),
        );

        $this->assertSame('Foo:', $view->children[0]->vars['label']);
        $this->assertSame('Bar:', $view->children[1]->vars['label']);
        $this->assertSame($expectedAttr[0], $view->children[0]->vars['attr']);
        $this->assertSame($expectedAttr[1], $view->children[1]->vars['attr']);
        $this->assertSame('_collection_entry', $view->children[0]->vars['unique_block_prefix']);
        $this->assertSame('_collection_entry', $view->children[1]->vars['unique_block_prefix']);
    }

    public function testEntryOptionsAsCallableWithPrototypeUsePrototypeOptionsData()
    {
        $data = array(
            'foo',
            'bar',
        );

        $view = $this->factory->create('Symfony\Component\Form\Extension\Core\Type\CollectionType', $data, array(
            'allow_add' => true,
            'prototype' => true,
            'prototype_name' => '__test__',
            'entry_options' => function ($entry) {
                return array(
                    'label' => ucfirst($entry).':',
                    'attr' => array('class' => $entry.'&class', 'data-upper' => strtoupper($entry)),
                );
            },
            'prototype_options' => array(
                'data' => 'baz',
                'required' => false,
            ),
        ))->createView();

        $expectedAttr = array(
            'entry1' => array('class' => 'foo&class', 'data-upper' => 'FOO'),
            'entry2' => array('class' => 'bar&class', 'data-upper' => 'BAR'),
            'prototype' => array('class' => 'baz&class', 'data-upper' => 'BAZ'),
        );

        $this->assertSame('Foo:', $view->children[0]->vars['label']);
        $this->assertSame('Bar:', $view->children[1]->vars['label']);
        $this->assertSame($expectedAttr['entry1'], $view->children[0]->vars['attr']);
        $this->assertSame($expectedAttr['entry2'], $view->children[1]->vars['attr']);
        $this->assertSame($expectedAttr['prototype'], $view->vars['prototype']->vars['attr']);
        $this->assertFalse($view->vars['prototype']->vars['required']);
        $this->assertSame('_collection_entry', $view->children[0]->vars['unique_block_prefix']);
        $this->assertSame('_collection_entry', $view->children[1]->vars['unique_block_prefix']);
        $this->assertSame('_collection_entry', $view->vars['prototype']->vars['unique_block_prefix']);
    }

    /**
     * @group legacy
     */
    public function testEntryOptionsAsCallableWithPrototypeUsePrototypeData()
    {
        $data = array(
            'foo',
            'bar',
        );

        $view = $this->factory->create('Symfony\Component\Form\Extension\Core\Type\CollectionType', $data, array(
            'allow_add' => true,
            'prototype' => true,
            'prototype_name' => '__test__',
            'entry_options' => function ($entry) {
                return array(
                    'label' => ucfirst($entry).':',
                    'attr' => array('class' => $entry.'&class', 'data-upper' => strtoupper($entry)),
                );
            },
            'prototype_options' => array(
                'required' => false,
            ),
            'prototype_data' => 'baz',
        ))->createView();

        $expectedAttr = array(
            'entry1' => array('class' => 'foo&class', 'data-upper' => 'FOO'),
            'entry2' => array('class' => 'bar&class', 'data-upper' => 'BAR'),
            'prototype' => array('class' => 'baz&class', 'data-upper' => 'BAZ'),
        );

        $this->assertSame('Foo:', $view->children[0]->vars['label']);
        $this->assertSame('Bar:', $view->children[1]->vars['label']);
        $this->assertSame('Baz:', $view->vars['prototype']->vars['label']);
        $this->assertSame($expectedAttr['entry1'], $view->children[0]->vars['attr']);
        $this->assertSame($expectedAttr['entry2'], $view->children[1]->vars['attr']);
        $this->assertSame($expectedAttr['prototype'], $view->vars['prototype']->vars['attr']);
        $this->assertFalse($view->vars['prototype']->vars['required']);
        $this->assertSame('_collection_entry', $view->children[0]->vars['unique_block_prefix']);
        $this->assertSame('_collection_entry', $view->children[1]->vars['unique_block_prefix']);
        $this->assertSame('_collection_entry', $view->vars['prototype']->vars['unique_block_prefix']);
    }
}
