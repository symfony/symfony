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

use Symfony\Component\Form\EntryTypeProviderInterface;
use Symfony\Component\Form\Extension\Core\Type\PolymorphicCollectionType;
use Symfony\Component\Form\Form;
use Symfony\Component\Form\Tests\Fixtures\Author;
use Symfony\Component\Form\Tests\Fixtures\AuthorEntryTypeProvider;
use Symfony\Component\Form\Tests\Fixtures\AuthorType;
use Symfony\Component\Form\Tests\Fixtures\BlockPrefixedFooTextType;
use Symfony\Component\Form\Tests\Fixtures\NumericEntryTypeProvider;
use Symfony\Component\OptionsResolver\Exception\InvalidOptionsException;
use Symfony\Component\OptionsResolver\Exception\MissingOptionsException;

class PolymorphicCollectionTypeTest extends BaseTypeTestCase
{
    public const TESTED_TYPE = PolymorphicCollectionType::class;

    protected function getTestOptions(): array
    {
        return [
            'entry_types' => [
                'text' => TextTypeTest::TESTED_TYPE,
                'number' => NumberTypeTest::TESTED_TYPE,
            ],
            'entry_type_provider' => new NumericEntryTypeProvider(),
        ];
    }

    public function testEntryTypeProviderIsRequired()
    {
        $this->expectException(MissingOptionsException::class);
        $this->expectExceptionMessage('The required option "entry_type_provider" is missing.');
        $this->factory->create(static::TESTED_TYPE, null, [
            'entry_types' => [
                'text' => TextTypeTest::TESTED_TYPE,
                'number' => NumberTypeTest::TESTED_TYPE,
            ],
        ]);
    }

    public function testEntryTypesIsRequired()
    {
        $this->expectException(MissingOptionsException::class);
        $this->expectExceptionMessage('The required option "entry_types" is missing.');
        $this->factory->create(static::TESTED_TYPE, null, [
            'entry_type_provider' => new NumericEntryTypeProvider(),
        ]);
    }

    public function testWithDifferentArrayKeysInEntriesOptions()
    {
        $this->expectException(InvalidOptionsException::class);
        $this->expectExceptionMessage('The "entry_options" option can only use keys of the "entry_types" option. Allowed keys are "text", "number", but got "foo", "1".');
        $this->factory->create(static::TESTED_TYPE, null, [
            'entry_types' => [
                'text' => TextTypeTest::TESTED_TYPE,
                'number' => NumberTypeTest::TESTED_TYPE,
            ],
            'entry_type_provider' => new NumericEntryTypeProvider(),
            'entry_options' => [
                'foo' => ['attr' => ['maxlength' => 20]],
                1 => ['attr' => ['maxlength' => 20]],
            ],
        ]);
    }

    public function testWithDifferentArrayKeysInPrototypesOptions()
    {
        $this->expectException(InvalidOptionsException::class);
        $this->expectExceptionMessage('The "prototype_options" option can only use keys of the "entry_types" option. Allowed keys are "text", "number", but got "0", "bar".');
        $this->factory->create(static::TESTED_TYPE, null, [
            'entry_types' => [
                'text' => TextTypeTest::TESTED_TYPE,
                'number' => NumberTypeTest::TESTED_TYPE,
            ],
            'entry_type_provider' => new NumericEntryTypeProvider(),
            'prototype_options' => [
                ['help' => 'text help'],
                'bar' => ['help' => 'author help'],
            ],
        ]);
    }

    public function testWithDifferentArrayKeysInPrototypesData()
    {
        $this->expectException(InvalidOptionsException::class);
        $this->expectExceptionMessage('The "prototype_data" option can only use keys of the "entry_types" option. Allowed keys are "text", "number", but got "foo".');
        $this->factory->create(static::TESTED_TYPE, null, [
            'entry_types' => [
                'text' => TextTypeTest::TESTED_TYPE,
                'number' => NumberTypeTest::TESTED_TYPE,
            ],
            'entry_type_provider' => new NumericEntryTypeProvider(),
            'prototype_data' => [
                'foo' => 'foo',
            ],
        ]);
    }

    public function testSetDataAdjustsSize()
    {
        $form = $this->factory->create(static::TESTED_TYPE, null, [
            'entry_types' => [
                'text' => TextTypeTest::TESTED_TYPE,
                'number' => NumberTypeTest::TESTED_TYPE,
            ],
            'entry_type_provider' => new NumericEntryTypeProvider(),
            'entry_options' => [
                'text' => ['attr' => ['maxlength' => 20]],
                'number' => ['attr' => ['max' => 5]],
            ],
        ]);
        $form->setData([123, 'foo']);

        $this->assertInstanceOf(Form::class, $form[0]);
        $this->assertInstanceOf(Form::class, $form[1]);
        $this->assertCount(2, $form);
        $this->assertEquals(123, $form[0]->getData());
        $this->assertEquals('foo', $form[1]->getData());
        $formAttrs0 = $form[0]->getConfig()->getOption('attr');
        $formAttrs1 = $form[1]->getConfig()->getOption('attr');
        $this->assertEquals(5, $formAttrs0['max']);
        $this->assertEquals(20, $formAttrs1['maxlength']);

        $form->setData([345]);
        $this->assertInstanceOf(Form::class, $form[0]);
        $this->assertArrayNotHasKey(1, $form);
        $this->assertCount(1, $form);
        $this->assertEquals(345, $form[0]->getData());
        $formAttrs0 = $form[0]->getConfig()->getOption('attr');
        $this->assertEquals(5, $formAttrs0['max']);
    }

    public function testNotResizedIfSubmittedWithMissingData()
    {
        $form = $this->factory->create(static::TESTED_TYPE, null, [
            'entry_types' => [
                'text' => TextTypeTest::TESTED_TYPE,
                'number' => NumberTypeTest::TESTED_TYPE,
            ],
            'entry_type_provider' => new NumericEntryTypeProvider(),
        ]);

        $form->setData(['foo@foo.com', 'bar@bar.com']);
        $form->submit(['foo@bar.com']);

        $this->assertTrue($form->has('0'));
        $this->assertTrue($form->has('1'));
        $this->assertEquals('foo@bar.com', $form[0]->getData());
        $this->assertEquals('', $form[1]->getData());
    }

    public function testResizedDownIfSubmittedWithMissingDataAndAllowDelete()
    {
        $form = $this->factory->create(static::TESTED_TYPE, null, [
            'entry_types' => [
                'text' => TextTypeTest::TESTED_TYPE,
                'number' => NumberTypeTest::TESTED_TYPE,
            ],
            'entry_type_provider' => new NumericEntryTypeProvider(),
            'allow_delete' => true,
        ]);

        $form->setData(['foo@foo.com', 'bar@bar.com']);
        $form->submit(['foo@foo.com']);

        $this->assertTrue($form->has('0'));
        $this->assertFalse($form->has('1'));
        $this->assertEquals('foo@foo.com', $form[0]->getData());
        $this->assertEquals(['foo@foo.com'], $form->getData());
    }

    public function testResizedDownIfSubmittedWithEmptyDataAndDeleteEmpty()
    {
        $form = $this->factory->create(static::TESTED_TYPE, null, [
            'entry_types' => [
                'text' => TextTypeTest::TESTED_TYPE,
                'number' => NumberTypeTest::TESTED_TYPE,
            ],
            'entry_type_provider' => new NumericEntryTypeProvider(),
            'allow_delete' => true,
            'delete_empty' => true,
        ]);

        $form->setData(['foo@foo.com', 'bar@bar.com']);
        $form->submit(['foo@foo.com', '']);

        $this->assertTrue($form->has('0'));
        $this->assertFalse($form->has('1'));
        $this->assertEquals('foo@foo.com', $form[0]->getData());
        $this->assertEquals(['foo@foo.com'], $form->getData());
    }

    public function testResizedDownWithDeleteEmptyCallable()
    {
        $form = $this->factory->create(static::TESTED_TYPE, null, [
            'entry_types' => [
                'text' => TextTypeTest::TESTED_TYPE,
                'author' => AuthorType::class,
            ],
            'entry_type_provider' => new AuthorEntryTypeProvider(),
            'allow_delete' => true,
            'delete_empty' => static fn (string|Author|null $data): bool => !$data || ($data instanceof Author && !$data->firstName),
        ]);

        $form->setData([new Author('Bob'), new Author('Alice'), 'John', 'Jane']);
        $form->submit([['firstName' => 'Bob'], ['firstName' => ''], 'John', '']);

        $this->assertTrue($form->has('0'));
        $this->assertFalse($form->has('1'));
        $this->assertTrue($form->has('2'));
        $this->assertFalse($form->has('3'));
        $this->assertEquals(new Author('Bob'), $form[0]->getData());
        $this->assertEquals('John', $form[2]->getData());
        $this->assertEquals([0 => new Author('Bob'), 2 => 'John'], $form->getData());
    }

    public function testResizedDownIfSubmittedWithCompoundEmptyDataDeleteEmptyAndNoDataClass()
    {
        $form = $this->factory->create(static::TESTED_TYPE, null, [
            'entry_types' => [
                'text' => TextTypeTest::TESTED_TYPE,
                'author' => AuthorType::class,
            ],
            'entry_type_provider' => new class implements EntryTypeProviderInterface {
                public function forModelData(mixed $data): int|string
                {
                    if ($data instanceof Author || \is_array($data)) {
                        return 'author';
                    }

                    return 'text';
                }

                public function forSubmittedData(mixed $data): int|string
                {
                    if (\is_array($data)) {
                        return 'author';
                    }

                    return 'text';
                }
            },
            'entry_options' => [
                'author' => ['data_class' => null],
            ],
            'allow_add' => true,
            'allow_delete' => true,
            'delete_empty' => static fn (string|array|null $data): bool => \is_array($data)
                ? empty($data['firstName'])
                : empty($data),
        ]);

        $form->setData([
            ['firstName' => 'first', 'lastName' => 'last'],
            'foo',
        ]);
        $form->submit([
            ['firstName' => 's_first', 'lastName' => 's_last'],
            's_foo',
            ['firstName' => '', 'lastName' => ''],
            '',
        ]);

        $this->assertTrue($form->has('0'));
        $this->assertTrue($form->has('1'));
        $this->assertFalse($form->has('2'));
        $this->assertFalse($form->has('3'));
        $this->assertEquals(['firstName' => 's_first', 'lastName' => 's_last'], $form[0]->getData());
        $this->assertEquals('s_foo', $form[1]->getData());
        $this->assertEquals([['firstName' => 's_first', 'lastName' => 's_last'], 's_foo'], $form->getData());
    }

    public function testDontAddEmptyDataIfDeleteEmpty()
    {
        $form = $this->factory->create(static::TESTED_TYPE, null, [
            'entry_types' => [
                'text' => TextTypeTest::TESTED_TYPE,
                'author' => AuthorType::class,
            ],
            'entry_type_provider' => new AuthorEntryTypeProvider(),
            'allow_add' => true,
            'delete_empty' => true,
        ]);

        $form->setData(['foo@foo.com']);
        $form->submit(['foo@foo.com', '']);

        $this->assertTrue($form->has('0'));
        $this->assertFalse($form->has('1'));
        $this->assertEquals('foo@foo.com', $form[0]->getData());
        $this->assertEquals(['foo@foo.com'], $form->getData());
    }

    public function testNoDeleteEmptyIfDeleteNotAllowed()
    {
        $form = $this->factory->create(static::TESTED_TYPE, null, [
            'entry_types' => [
                'text' => TextTypeTest::TESTED_TYPE,
                'author' => AuthorType::class,
            ],
            'entry_type_provider' => new AuthorEntryTypeProvider(),
            'allow_delete' => false,
            'delete_empty' => true,
        ]);

        $form->setData(['foo@foo.com', new Author('Bob')]);
        $form->submit(['', ['firstName' => '']]);

        $this->assertTrue($form->has('0'));
        $this->assertTrue($form->has('1'));
        $this->assertEquals('', $form[0]->getData());
        $this->assertEquals(new Author(''), $form[1]->getData());
    }

    public function testNotResizedIfSubmittedWithExtraData()
    {
        $form = $this->factory->create(static::TESTED_TYPE, null, [
            'entry_types' => [
                'text' => TextTypeTest::TESTED_TYPE,
                'author' => AuthorType::class,
            ],
            'entry_type_provider' => new AuthorEntryTypeProvider(),
        ]);
        $form->setData(['foo@bar.com', new Author('Bob')]);
        $form->submit(['foo@foo.com', ['firstName' => 'Alice'], 'bar@bar.com', ['firstName' => 'John']]);

        $this->assertTrue($form->has('0'));
        $this->assertTrue($form->has('1'));
        $this->assertFalse($form->has('2'));
        $this->assertFalse($form->has('3'));
        $this->assertEquals('foo@foo.com', $form[0]->getData());
        $this->assertEquals(new Author('Alice'), $form[1]->getData());
    }

    public function testResizedUpIfSubmittedWithExtraDataAndAllowAdd()
    {
        $form = $this->factory->create(static::TESTED_TYPE, null, [
            'entry_types' => [
                'text' => TextTypeTest::TESTED_TYPE,
                'author' => AuthorType::class,
            ],
            'entry_type_provider' => new AuthorEntryTypeProvider(),
            'allow_add' => true,
            'keep_as_list' => true,
        ]);
        $form->setData(['foo@bar.com', new Author('John')]);
        $form->submit(['foo@bar.com', ['firstName' => 'Bob'], 'bar@bar.com', ['firstName' => 'Alice']]);

        $this->assertTrue($form->has('0'));
        $this->assertTrue($form->has('1'));
        $this->assertTrue($form->has('2'));
        $this->assertTrue($form->has('3'));
        $this->assertEquals('foo@bar.com', $form[0]->getData());
        $this->assertEquals('bar@bar.com', $form[2]->getData());
        $this->assertEquals(['foo@bar.com', new Author('Bob'), 'bar@bar.com', new Author('Alice')], $form->getData());
    }

    public function testPrototypeMultipartPropagation()
    {
        $form = $this->factory
            ->create(static::TESTED_TYPE, null, [
                'entry_types' => [
                    'text' => TextTypeTest::TESTED_TYPE,
                    'file' => FileTypeTest::TESTED_TYPE,
                ],
                'entry_type_provider' => new class implements EntryTypeProviderInterface {
                    public function forModelData(mixed $data): int|string
                    {
                        return 'text';
                    }

                    public function forSubmittedData(mixed $data): int|string
                    {
                        return 'text';
                    }
                },
                'allow_add' => true,
                'prototype' => true,
            ])
        ;

        $this->assertTrue($form->createView()->vars['multipart']);
    }

    public function testPrototypeNameOption()
    {
        $form = $this->factory->create(static::TESTED_TYPE, null, [
            'entry_types' => [
                'text' => TextTypeTest::TESTED_TYPE,
                'author' => AuthorType::class,
            ],
            'entry_type_provider' => new AuthorEntryTypeProvider(),
            'prototype' => true,
            'allow_add' => true,
        ]);

        $this->assertSame('__name__', $form->getConfig()->getAttribute('prototypes')['text']->getName());
        $this->assertSame('__name__', $form->getConfig()->getAttribute('prototypes')['author']->getName());

        $form = $this->factory->create(static::TESTED_TYPE, null, [
            'entry_types' => [
                'text' => TextTypeTest::TESTED_TYPE,
                'author' => AuthorType::class,
            ],
            'entry_type_provider' => new AuthorEntryTypeProvider(),
            'prototype' => true,
            'allow_add' => true,
            'prototype_name' => '__test__',
        ]);

        $this->assertSame('__test__', $form->getConfig()->getAttribute('prototypes')['text']->getName());
        $this->assertSame('__test__', $form->getConfig()->getAttribute('prototypes')['author']->getName());
    }

    public function testPrototypeDefaultLabel()
    {
        $form = $this->factory->create(static::TESTED_TYPE, [], [
            'entry_types' => [
                'text' => TextTypeTest::TESTED_TYPE,
                'author' => AuthorType::class,
            ],
            'entry_type_provider' => new AuthorEntryTypeProvider(),
            'allow_add' => true,
            'prototype' => true,
            'prototype_name' => '__test__',
        ]);

        $this->assertSame('__test__label__', $form->createView()->vars['prototypes']['text']->vars['label']);
        $this->assertSame('__test__label__', $form->createView()->vars['prototypes']['author']->vars['label']);
    }

    public function testPrototypeData()
    {
        $form = $this->factory->create(static::TESTED_TYPE, [], [
            'entry_types' => [
                'text' => TextTypeTest::TESTED_TYPE,
                'author' => AuthorType::class,
            ],
            'entry_type_provider' => new AuthorEntryTypeProvider(),
            'prototype' => true,
            'prototype_data' => [
                'text' => 'foo',
                'author' => new Author('Bob'),
            ],
            'entry_options' => [
                'text' => [
                    'data' => 'bar',
                    'label' => false,
                ],
                'author' => [
                    'data' => new Author('Alice'),
                    'label' => false,
                ],
            ],
            'allow_add' => true,
        ]);

        $this->assertSame('foo', $form->createView()->vars['prototypes']['text']->vars['value']);
        $this->assertEquals(new Author('Bob'), $form->createView()->vars['prototypes']['author']->vars['value']);
        $this->assertFalse($form->createView()->vars['prototypes']['text']->vars['label']);
        $this->assertFalse($form->createView()->vars['prototypes']['author']->vars['label']);
    }

    public function testPrototypeDefaultRequired()
    {
        $form = $this->factory->create(static::TESTED_TYPE, [], [
            'entry_types' => [
                'text' => TextTypeTest::TESTED_TYPE,
                'author' => AuthorType::class,
            ],
            'entry_type_provider' => new AuthorEntryTypeProvider(),
            'allow_add' => true,
            'prototype' => true,
            'prototype_name' => '__test__',
        ]);

        $this->assertTrue($form->createView()->vars['prototypes']['text']->vars['required']);
        $this->assertTrue($form->createView()->vars['prototypes']['author']->vars['required']);
    }

    public function testPrototypeSetNotRequired()
    {
        $form = $this->factory->create(static::TESTED_TYPE, [], [
            'entry_types' => [
                'text' => TextTypeTest::TESTED_TYPE,
                'author' => AuthorType::class,
            ],
            'entry_type_provider' => new AuthorEntryTypeProvider(),
            'allow_add' => true,
            'prototype' => true,
            'prototype_name' => '__test__',
            'required' => false,
        ]);

        $this->assertFalse($form->createView()->vars['required'], 'collection is not required');
        $this->assertFalse($form->createView()->vars['prototypes']['text']->vars['required'], '"prototype" should not be required');
        $this->assertFalse($form->createView()->vars['prototypes']['author']->vars['required'], '"prototype" should not be required');
    }

    public function testPrototypeSetNotRequiredIfParentNotRequired()
    {
        $child = $this->factory->create(static::TESTED_TYPE, [], [
            'entry_types' => [
                'text' => TextTypeTest::TESTED_TYPE,
                'author' => AuthorType::class,
            ],
            'entry_type_provider' => new AuthorEntryTypeProvider(),
            'allow_add' => true,
            'prototype' => true,
            'prototype_name' => '__test__',
        ]);

        $parent = $this->factory->create(FormTypeTest::TESTED_TYPE, [], [
            'required' => false,
        ]);

        $child->setParent($parent);
        $this->assertFalse($parent->createView()->vars['required'], 'Parent is not required');
        $this->assertFalse($child->createView()->vars['required'], 'Child is not required');
        $this->assertFalse($child->createView()->vars['prototypes']['text']->vars['required'], '"prototype" should not be required');
        $this->assertFalse($child->createView()->vars['prototypes']['author']->vars['required'], '"prototype" should not be required');
    }

    public function testPrototypeOptionsOverrideEntryOptions()
    {
        $form = $this->factory->create(static::TESTED_TYPE, [], [
            'entry_types' => [
                'text' => TextTypeTest::TESTED_TYPE,
                'author' => AuthorType::class,
            ],
            'entry_type_provider' => new AuthorEntryTypeProvider(),
            'allow_add' => true,
            'prototype' => true,
            'entry_options' => [
                'text' => ['help' => null],
                'author' => ['help' => 'foo'],
            ],
            'prototype_options' => [
                'text' => ['help' => 'foo'],
                'author' => ['help' => 'bar'],
            ],
        ]);

        $this->assertSame('foo', $form->createView()->vars['prototypes']['text']->vars['help']);
        $this->assertSame('bar', $form->createView()->vars['prototypes']['author']->vars['help']);
    }

    public function testPrototypeOptionsAppliedToNewFields()
    {
        $form = $this->factory->create(static::TESTED_TYPE, ['first'], [
            'entry_types' => [
                'text' => TextTypeTest::TESTED_TYPE,
                'author' => AuthorType::class,
            ],
            'entry_type_provider' => new AuthorEntryTypeProvider(),
            'allow_add' => true,
            'prototype' => true,
            'entry_options' => [
                'text' => ['disabled' => true],
            ],
            'prototype_options' => [
                'text' => ['disabled' => false],
            ],
        ]);

        $form->submit(['first_changed', 'second']);

        $this->assertTrue($form->has('0'));
        $this->assertTrue($form->has('1'));
        $this->assertSame('first', $form[0]->getData());
        $this->assertSame('second', $form[1]->getData());
        $this->assertSame(['first', 'second'], $form->getData());
    }

    public function testEntriesBlockPrefixes()
    {
        $collectionView = $this->factory->createNamed('fields', static::TESTED_TYPE, ['foo', new Author('Bob')], [
            'entry_types' => [
                'text' => TextTypeTest::TESTED_TYPE,
                'author' => AuthorType::class,
            ],
            'entry_type_provider' => new AuthorEntryTypeProvider(),
            'allow_add' => true,
        ])
            ->createView()
        ;

        $expectedBlockPrefixesForText = [
            'form',
            'collection_entry',
            'text',
            '_fields_entry',
        ];
        $expectedBlockPrefixesForAuthor = [
            'form',
            'collection_entry',
            'author',
            '_fields_entry',
        ];

        $this->assertCount(2, $collectionView);
        $this->assertSame($expectedBlockPrefixesForText, $collectionView[0]->vars['block_prefixes']);
        $this->assertSame($expectedBlockPrefixesForAuthor, $collectionView[1]->vars['block_prefixes']);
        $this->assertSame($expectedBlockPrefixesForText, $collectionView->vars['prototypes']['text']->vars['block_prefixes']);
        $this->assertSame($expectedBlockPrefixesForAuthor, $collectionView->vars['prototypes']['author']->vars['block_prefixes']);
    }

    public function testEntriesBlockPrefixesWithCustomBlockPrefix()
    {
        $collectionView = $this->factory->createNamed('fields', static::TESTED_TYPE, ['foo', 123.4, new Author('Bob')], [
            'entry_types' => [
                'text' => TextTypeTest::TESTED_TYPE,
                'number' => NumberTypeTest::TESTED_TYPE,
                'author' => AuthorType::class,
            ],
            'entry_type_provider' => new class implements EntryTypeProviderInterface {
                public function forModelData(mixed $data): int|string
                {
                    if ($data instanceof Author) {
                        return 'author';
                    }

                    return is_numeric($data) ? 'number' : 'text';
                }

                public function forSubmittedData(mixed $data): int|string
                {
                    if (\is_array($data)) {
                        return 'author';
                    }

                    return is_numeric($data) ? 'number' : 'text';
                }
            },
            'allow_add' => true,
            'entry_options' => [
                'text' => ['block_prefix' => 'foo'],
                'author' => ['block_prefix' => 'bar'],
            ],
        ])
            ->createView()
        ;

        $expectedBlockPrefixesForText = [
            'form',
            'collection_entry',
            'text',
            'foo',
            '_fields_entry',
        ];
        $expectedBlockPrefixesForNumber = [
            'form',
            'collection_entry',
            'number',
            '_fields_entry',
        ];
        $expectedBlockPrefixesForAuthor = [
            'form',
            'collection_entry',
            'author',
            'bar',
            '_fields_entry',
        ];

        $this->assertCount(3, $collectionView);
        $this->assertSame($expectedBlockPrefixesForText, $collectionView[0]->vars['block_prefixes']);
        $this->assertSame($expectedBlockPrefixesForNumber, $collectionView[1]->vars['block_prefixes']);
        $this->assertSame($expectedBlockPrefixesForAuthor, $collectionView[2]->vars['block_prefixes']);
        $this->assertSame($expectedBlockPrefixesForText, $collectionView->vars['prototypes']['text']->vars['block_prefixes']);
        $this->assertSame($expectedBlockPrefixesForNumber, $collectionView->vars['prototypes']['number']->vars['block_prefixes']);
        $this->assertSame($expectedBlockPrefixesForAuthor, $collectionView->vars['prototypes']['author']->vars['block_prefixes']);
    }

    public function testEntriesBlockPrefixesWithCustomBlockPrefixedType()
    {
        $collectionView = $this->factory->createNamed('fields', static::TESTED_TYPE, [''], [
            'entry_types' => [
                'number' => NumberTypeTest::TESTED_TYPE,
                'block_prefixed_foo_text' => BlockPrefixedFooTextType::class,
            ],
            'entry_type_provider' => new class implements EntryTypeProviderInterface {
                public function forModelData(mixed $data): int|string
                {
                    return is_numeric($data) ? 'number' : 'block_prefixed_foo_text';
                }

                public function forSubmittedData(mixed $data): int|string
                {
                    return is_numeric($data) ? 'number' : 'block_prefixed_foo_text';
                }
            },
            'allow_add' => true,
        ])
            ->createView()
        ;

        $expectedBlockPrefixesForNumber = [
            'form',
            'collection_entry',
            'number',
            '_fields_entry',
        ];
        $expectedBlockPrefixesForBlockPrefixedFooText = [
            'form',
            'collection_entry',
            'block_prefixed_foo_text',
            'foo',
            '_fields_entry',
        ];

        $this->assertCount(1, $collectionView);
        $this->assertSame($expectedBlockPrefixesForBlockPrefixedFooText, $collectionView[0]->vars['block_prefixes']);
        $this->assertSame($expectedBlockPrefixesForNumber, $collectionView->vars['prototypes']['number']->vars['block_prefixes']);
        $this->assertSame($expectedBlockPrefixesForBlockPrefixedFooText, $collectionView->vars['prototypes']['block_prefixed_foo_text']->vars['block_prefixes']);
    }

    public function testPrototypeBlockPrefixesWithCustomBlockPrefix()
    {
        $collectionView = $this->factory->createNamed('fields', static::TESTED_TYPE, [], [
            'entry_types' => [
                'text' => TextTypeTest::TESTED_TYPE,
                'author' => AuthorType::class,
            ],
            'entry_type_provider' => new AuthorEntryTypeProvider(),
            'allow_add' => true,
            'entry_options' => [
                'author' => ['block_prefix' => 'field'],
            ],
        ])
            ->createView()
        ;

        $expectedBlockPrefixesForText = [
            'form',
            'collection_entry',
            'text',
            '_fields_entry',
        ];
        $expectedBlockPrefixesForAuthorText = [
            'form',
            'collection_entry',
            'author',
            'field',
            '_fields_entry',
        ];

        $this->assertCount(0, $collectionView);
        $this->assertSame($expectedBlockPrefixesForText, $collectionView->vars['prototypes']['text']->vars['block_prefixes']);
        $this->assertSame($expectedBlockPrefixesForAuthorText, $collectionView->vars['prototypes']['author']->vars['block_prefixes']);
    }

    public function testSubmitNull($expected = null, $norm = null, $view = null)
    {
        parent::testSubmitNull([], [], []);
    }

    public function testSubmitNullUsesDefaultEmptyData($emptyData = [], $expectedData = [])
    {
        // the resize listener always sets an empty array
        parent::testSubmitNullUsesDefaultEmptyData($emptyData, $expectedData);
    }
}
