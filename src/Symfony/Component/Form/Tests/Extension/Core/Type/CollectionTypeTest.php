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

use Symfony\Component\Form\Exception\UnexpectedTypeException;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Form;
use Symfony\Component\Form\Tests\Fixtures\Author;
use Symfony\Component\Form\Tests\Fixtures\AuthorType;
use Symfony\Component\Form\Tests\Fixtures\BlockPrefixedFooTextType;

class CollectionTypeTest extends BaseTypeTest
{
    public const TESTED_TYPE = 'Symfony\Component\Form\Extension\Core\Type\CollectionType';

    public function testContainsNoChildByDefault()
    {
        $form = $this->factory->create(static::TESTED_TYPE, null, [
            'entry_type' => TextTypeTest::TESTED_TYPE,
        ]);

        self::assertCount(0, $form);
    }

    public function testSetDataAdjustsSize()
    {
        $form = $this->factory->create(static::TESTED_TYPE, null, [
            'entry_type' => TextTypeTest::TESTED_TYPE,
            'entry_options' => [
                'attr' => ['maxlength' => 20],
            ],
        ]);
        $form->setData(['foo@foo.com', 'foo@bar.com']);

        self::assertInstanceOf(Form::class, $form[0]);
        self::assertInstanceOf(Form::class, $form[1]);
        self::assertCount(2, $form);
        self::assertEquals('foo@foo.com', $form[0]->getData());
        self::assertEquals('foo@bar.com', $form[1]->getData());
        $formAttrs0 = $form[0]->getConfig()->getOption('attr');
        $formAttrs1 = $form[1]->getConfig()->getOption('attr');
        self::assertEquals(20, $formAttrs0['maxlength']);
        self::assertEquals(20, $formAttrs1['maxlength']);

        $form->setData(['foo@baz.com']);
        self::assertInstanceOf(Form::class, $form[0]);
        self::assertArrayNotHasKey(1, $form);
        self::assertCount(1, $form);
        self::assertEquals('foo@baz.com', $form[0]->getData());
        $formAttrs0 = $form[0]->getConfig()->getOption('attr');
        self::assertEquals(20, $formAttrs0['maxlength']);
    }

    public function testThrowsExceptionIfObjectIsNotTraversable()
    {
        $form = $this->factory->create(static::TESTED_TYPE, null, [
            'entry_type' => TextTypeTest::TESTED_TYPE,
        ]);
        self::expectException(UnexpectedTypeException::class);
        $form->setData(new \stdClass());
    }

    public function testNotResizedIfSubmittedWithMissingData()
    {
        $form = $this->factory->create(static::TESTED_TYPE, null, [
            'entry_type' => TextTypeTest::TESTED_TYPE,
        ]);
        $form->setData(['foo@foo.com', 'bar@bar.com']);
        $form->submit(['foo@bar.com']);

        self::assertTrue($form->has('0'));
        self::assertTrue($form->has('1'));
        self::assertEquals('foo@bar.com', $form[0]->getData());
        self::assertEquals('', $form[1]->getData());
    }

    public function testResizedDownIfSubmittedWithMissingDataAndAllowDelete()
    {
        $form = $this->factory->create(static::TESTED_TYPE, null, [
            'entry_type' => TextTypeTest::TESTED_TYPE,
            'allow_delete' => true,
        ]);
        $form->setData(['foo@foo.com', 'bar@bar.com']);
        $form->submit(['foo@foo.com']);

        self::assertTrue($form->has('0'));
        self::assertFalse($form->has('1'));
        self::assertEquals('foo@foo.com', $form[0]->getData());
        self::assertEquals(['foo@foo.com'], $form->getData());
    }

    public function testResizedDownIfSubmittedWithEmptyDataAndDeleteEmpty()
    {
        $form = $this->factory->create(static::TESTED_TYPE, null, [
            'entry_type' => TextTypeTest::TESTED_TYPE,
            'allow_delete' => true,
            'delete_empty' => true,
        ]);

        $form->setData(['foo@foo.com', 'bar@bar.com']);
        $form->submit(['foo@foo.com', '']);

        self::assertTrue($form->has('0'));
        self::assertFalse($form->has('1'));
        self::assertEquals('foo@foo.com', $form[0]->getData());
        self::assertEquals(['foo@foo.com'], $form->getData());
    }

    public function testResizedDownWithDeleteEmptyCallable()
    {
        $form = $this->factory->create(static::TESTED_TYPE, null, [
            'entry_type' => AuthorType::class,
            'allow_delete' => true,
            'delete_empty' => function (Author $obj = null) {
                return null === $obj || empty($obj->firstName);
            },
        ]);

        $form->setData([new Author('Bob'), new Author('Alice')]);
        $form->submit([['firstName' => 'Bob'], ['firstName' => '']]);

        self::assertTrue($form->has('0'));
        self::assertFalse($form->has('1'));
        self::assertEquals(new Author('Bob'), $form[0]->getData());
        self::assertEquals([new Author('Bob')], $form->getData());
    }

    public function testResizedDownIfSubmittedWithCompoundEmptyDataDeleteEmptyAndNoDataClass()
    {
        $form = $this->factory->create(static::TESTED_TYPE, null, [
            'entry_type' => AuthorType::class,
            // If the field is not required, no new Author will be created if the
            // form is completely empty
            'entry_options' => ['data_class' => null],
            'allow_add' => true,
            'allow_delete' => true,
            'delete_empty' => function ($author) {
                return empty($author['firstName']);
            },
        ]);
        $form->setData([['firstName' => 'first', 'lastName' => 'last']]);
        $form->submit([
            ['firstName' => 's_first', 'lastName' => 's_last'],
            ['firstName' => '', 'lastName' => ''],
        ]);
        self::assertTrue($form->has('0'));
        self::assertFalse($form->has('1'));
        self::assertEquals(['firstName' => 's_first', 'lastName' => 's_last'], $form[0]->getData());
        self::assertEquals([['firstName' => 's_first', 'lastName' => 's_last']], $form->getData());
    }

    public function testDontAddEmptyDataIfDeleteEmpty()
    {
        $form = $this->factory->create(static::TESTED_TYPE, null, [
            'entry_type' => TextTypeTest::TESTED_TYPE,
            'allow_add' => true,
            'delete_empty' => true,
        ]);

        $form->setData(['foo@foo.com']);
        $form->submit(['foo@foo.com', '']);

        self::assertTrue($form->has('0'));
        self::assertFalse($form->has('1'));
        self::assertEquals('foo@foo.com', $form[0]->getData());
        self::assertEquals(['foo@foo.com'], $form->getData());
    }

    public function testNoDeleteEmptyIfDeleteNotAllowed()
    {
        $form = $this->factory->create(static::TESTED_TYPE, null, [
            'entry_type' => TextTypeTest::TESTED_TYPE,
            'allow_delete' => false,
            'delete_empty' => true,
        ]);

        $form->setData(['foo@foo.com']);
        $form->submit(['']);

        self::assertTrue($form->has('0'));
        self::assertEquals('', $form[0]->getData());
    }

    public function testResizedDownIfSubmittedWithCompoundEmptyDataAndDeleteEmpty()
    {
        $form = $this->factory->create(static::TESTED_TYPE, null, [
            'entry_type' => 'Symfony\Component\Form\Tests\Fixtures\AuthorType',
            // If the field is not required, no new Author will be created if the
            // form is completely empty
            'entry_options' => ['required' => false],
            'allow_add' => true,
            'delete_empty' => true,
        ]);

        $form->setData([new Author('first', 'last')]);
        $form->submit([
            ['firstName' => 's_first', 'lastName' => 's_last'],
            ['firstName' => '', 'lastName' => ''],
        ]);

        self::assertTrue($form->has('0'));
        self::assertFalse($form->has('1'));
        self::assertEquals(new Author('s_first', 's_last'), $form[0]->getData());
        self::assertEquals([new Author('s_first', 's_last')], $form->getData());
    }

    public function testNotDeleteEmptyIfInvalid()
    {
        $form = $this->factory->create(static::TESTED_TYPE, null, [
            'entry_type' => ChoiceType::class,
            'entry_options' => [
                'choices' => ['a', 'b'],
            ],
            'allow_add' => true,
            'allow_delete' => true,
            'delete_empty' => true,
        ]);

        $form->submit(['a', 'x', '']);

        self::assertSame(['a'], $form->getData());
        self::assertCount(2, $form);
        self::assertTrue($form->has('1'));
        self::assertFalse($form[1]->isValid());
        self::assertNull($form[1]->getData());
        self::assertSame('x', $form[1]->getViewData());
    }

    public function testNotResizedIfSubmittedWithExtraData()
    {
        $form = $this->factory->create(static::TESTED_TYPE, null, [
            'entry_type' => TextTypeTest::TESTED_TYPE,
        ]);
        $form->setData(['foo@bar.com']);
        $form->submit(['foo@foo.com', 'bar@bar.com']);

        self::assertTrue($form->has('0'));
        self::assertFalse($form->has('1'));
        self::assertEquals('foo@foo.com', $form[0]->getData());
    }

    public function testResizedUpIfSubmittedWithExtraDataAndAllowAdd()
    {
        $form = $this->factory->create(static::TESTED_TYPE, null, [
            'entry_type' => TextTypeTest::TESTED_TYPE,
            'allow_add' => true,
        ]);
        $form->setData(['foo@bar.com']);
        $form->submit(['foo@bar.com', 'bar@bar.com']);

        self::assertTrue($form->has('0'));
        self::assertTrue($form->has('1'));
        self::assertEquals('foo@bar.com', $form[0]->getData());
        self::assertEquals('bar@bar.com', $form[1]->getData());
        self::assertEquals(['foo@bar.com', 'bar@bar.com'], $form->getData());
    }

    public function testAllowAddButNoPrototype()
    {
        $form = $this->factory->create(static::TESTED_TYPE, null, [
            'entry_type' => FormTypeTest::TESTED_TYPE,
            'allow_add' => true,
            'prototype' => false,
        ]);

        self::assertFalse($form->has('__name__'));
    }

    public function testPrototypeMultipartPropagation()
    {
        $form = $this->factory
            ->create(static::TESTED_TYPE, null, [
                'entry_type' => FileTypeTest::TESTED_TYPE,
                'allow_add' => true,
                'prototype' => true,
            ])
        ;

        self::assertTrue($form->createView()->vars['multipart']);
    }

    public function testGetDataDoesNotContainsPrototypeNameBeforeDataAreSet()
    {
        $form = $this->factory->create(static::TESTED_TYPE, [], [
            'entry_type' => FileTypeTest::TESTED_TYPE,
            'prototype' => true,
            'allow_add' => true,
        ]);

        $data = $form->getData();
        self::assertArrayNotHasKey('__name__', $data);
    }

    public function testGetDataDoesNotContainsPrototypeNameAfterDataAreSet()
    {
        $form = $this->factory->create(static::TESTED_TYPE, [], [
            'entry_type' => FileTypeTest::TESTED_TYPE,
            'allow_add' => true,
            'prototype' => true,
        ]);

        $form->setData(['foobar.png']);
        $data = $form->getData();
        self::assertArrayNotHasKey('__name__', $data);
    }

    public function testPrototypeNameOption()
    {
        $form = $this->factory->create(static::TESTED_TYPE, null, [
            'entry_type' => FormTypeTest::TESTED_TYPE,
            'prototype' => true,
            'allow_add' => true,
        ]);

        self::assertSame('__name__', $form->getConfig()->getAttribute('prototype')->getName(), '__name__ is the default');

        $form = $this->factory->create(static::TESTED_TYPE, null, [
            'entry_type' => FormTypeTest::TESTED_TYPE,
            'prototype' => true,
            'allow_add' => true,
            'prototype_name' => '__test__',
        ]);

        self::assertSame('__test__', $form->getConfig()->getAttribute('prototype')->getName());
    }

    public function testPrototypeDefaultLabel()
    {
        $form = $this->factory->create(static::TESTED_TYPE, [], [
            'entry_type' => FileTypeTest::TESTED_TYPE,
            'allow_add' => true,
            'prototype' => true,
            'prototype_name' => '__test__',
        ]);

        self::assertSame('__test__label__', $form->createView()->vars['prototype']->vars['label']);
    }

    public function testPrototypeData()
    {
        $form = $this->factory->create(static::TESTED_TYPE, [], [
            'allow_add' => true,
            'prototype' => true,
            'prototype_data' => 'foo',
            'entry_type' => TextTypeTest::TESTED_TYPE,
            'entry_options' => [
                'data' => 'bar',
                'label' => false,
            ],
        ]);

        self::assertSame('foo', $form->createView()->vars['prototype']->vars['value']);
        self::assertFalse($form->createView()->vars['prototype']->vars['label']);
    }

    public function testPrototypeDefaultRequired()
    {
        $form = $this->factory->create(static::TESTED_TYPE, [], [
            'entry_type' => FileTypeTest::TESTED_TYPE,
            'allow_add' => true,
            'prototype' => true,
            'prototype_name' => '__test__',
        ]);

        self::assertTrue($form->createView()->vars['prototype']->vars['required']);
    }

    public function testPrototypeSetNotRequired()
    {
        $form = $this->factory->create(static::TESTED_TYPE, [], [
            'entry_type' => FileTypeTest::TESTED_TYPE,
            'allow_add' => true,
            'prototype' => true,
            'prototype_name' => '__test__',
            'required' => false,
        ]);

        self::assertFalse($form->createView()->vars['required'], 'collection is not required');
        self::assertFalse($form->createView()->vars['prototype']->vars['required'], '"prototype" should not be required');
    }

    public function testPrototypeSetNotRequiredIfParentNotRequired()
    {
        $child = $this->factory->create(static::TESTED_TYPE, [], [
            'entry_type' => FileTypeTest::TESTED_TYPE,
            'allow_add' => true,
            'prototype' => true,
            'prototype_name' => '__test__',
        ]);

        $parent = $this->factory->create(FormTypeTest::TESTED_TYPE, [], [
            'required' => false,
        ]);

        $child->setParent($parent);
        self::assertFalse($parent->createView()->vars['required'], 'Parent is not required');
        self::assertFalse($child->createView()->vars['required'], 'Child is not required');
        self::assertFalse($child->createView()->vars['prototype']->vars['required'], '"Prototype" should not be required');
    }

    public function testPrototypeNotOverrideRequiredByEntryOptionsInFavorOfParent()
    {
        $child = $this->factory->create(static::TESTED_TYPE, [], [
            'entry_type' => FileTypeTest::TESTED_TYPE,
            'allow_add' => true,
            'prototype' => true,
            'prototype_name' => '__test__',
            'entry_options' => [
                'required' => true,
            ],
        ]);

        $parent = $this->factory->create(FormTypeTest::TESTED_TYPE, [], [
            'required' => false,
        ]);

        $child->setParent($parent);

        self::assertFalse($parent->createView()->vars['required'], 'Parent is not required');
        self::assertFalse($child->createView()->vars['required'], 'Child is not required');
        self::assertFalse($child->createView()->vars['prototype']->vars['required'], '"Prototype" should not be required');
    }

    public function testEntriesBlockPrefixes()
    {
        $collectionView = $this->factory->createNamed('fields', static::TESTED_TYPE, [''], [
            'allow_add' => true,
        ])
            ->createView()
        ;

        $expectedBlockPrefixes = [
            'form',
            'collection_entry',
            'text',
            '_fields_entry',
        ];

        self::assertCount(1, $collectionView);
        self::assertSame($expectedBlockPrefixes, $collectionView[0]->vars['block_prefixes']);
        self::assertSame($expectedBlockPrefixes, $collectionView->vars['prototype']->vars['block_prefixes']);
    }

    public function testEntriesBlockPrefixesWithCustomBlockPrefix()
    {
        $collectionView = $this->factory->createNamed('fields', static::TESTED_TYPE, [''], [
            'allow_add' => true,
            'entry_options' => ['block_prefix' => 'field'],
        ])
            ->createView()
        ;

        $expectedBlockPrefixes = [
            'form',
            'collection_entry',
            'text',
            'field',
            '_fields_entry',
        ];

        self::assertCount(1, $collectionView);
        self::assertSame($expectedBlockPrefixes, $collectionView[0]->vars['block_prefixes']);
        self::assertSame($expectedBlockPrefixes, $collectionView->vars['prototype']->vars['block_prefixes']);
    }

    public function testEntriesBlockPrefixesWithCustomBlockPrefixedType()
    {
        $collectionView = $this->factory->createNamed('fields', static::TESTED_TYPE, [''], [
            'allow_add' => true,
            'entry_type' => BlockPrefixedFooTextType::class,
        ])
            ->createView()
        ;

        $expectedBlockPrefixes = [
            'form',
            'collection_entry',
            'block_prefixed_foo_text',
            'foo',
            '_fields_entry',
        ];

        self::assertCount(1, $collectionView);
        self::assertSame($expectedBlockPrefixes, $collectionView[0]->vars['block_prefixes']);
        self::assertSame($expectedBlockPrefixes, $collectionView->vars['prototype']->vars['block_prefixes']);
    }

    public function testPrototypeBlockPrefixesWithCustomBlockPrefix()
    {
        $collectionView = $this->factory->createNamed('fields', static::TESTED_TYPE, [], [
            'allow_add' => true,
            'entry_options' => ['block_prefix' => 'field'],
        ])
            ->createView()
        ;

        $expectedBlockPrefixes = [
            'form',
            'collection_entry',
            'text',
            'field',
            '_fields_entry',
        ];

        self::assertCount(0, $collectionView);
        self::assertSame($expectedBlockPrefixes, $collectionView->vars['prototype']->vars['block_prefixes']);
    }

    public function testSubmitNull($expected = null, $norm = null, $view = null)
    {
        parent::testSubmitNull([], [], []);
    }

    public function testSubmitNullUsesDefaultEmptyData($emptyData = [], $expectedData = [])
    {
        // resize form listener always set an empty array
        parent::testSubmitNullUsesDefaultEmptyData($emptyData, $expectedData);
    }
}
