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

use Symfony\Component\Form\ChoiceList\View\ChoiceGroupView;
use Symfony\Component\Form\ChoiceList\View\ChoiceView;

class ChoiceTypeTest extends BaseTypeTest
{
    const TESTED_TYPE = 'Symfony\Component\Form\Extension\Core\Type\ChoiceType';

    private $choices = [
        'Bernhard' => 'a',
        'Fabien' => 'b',
        'Kris' => 'c',
        'Jon' => 'd',
        'Roman' => 'e',
    ];

    private $scalarChoices = [
        'Yes' => true,
        'No' => false,
        'n/a' => '',
    ];

    private $booleanChoicesWithNull = [
        'Yes' => true,
        'No' => false,
        'n/a' => null,
    ];

    private $numericChoicesFlipped = [
        0 => 'Bernhard',
        1 => 'Fabien',
        2 => 'Kris',
        3 => 'Jon',
        4 => 'Roman',
    ];

    private $objectChoices;

    protected $groupedChoices = [
        'Symfony' => [
            'Bernhard' => 'a',
            'Fabien' => 'b',
            'Kris' => 'c',
        ],
        'Doctrine' => [
            'Jon' => 'd',
            'Roman' => 'e',
        ],
    ];

    protected function setUp()
    {
        parent::setUp();

        $this->objectChoices = [
            (object) ['id' => 1, 'name' => 'Bernhard'],
            (object) ['id' => 2, 'name' => 'Fabien'],
            (object) ['id' => 3, 'name' => 'Kris'],
            (object) ['id' => 4, 'name' => 'Jon'],
            (object) ['id' => 5, 'name' => 'Roman'],
        ];
    }

    protected function tearDown()
    {
        parent::tearDown();

        $this->objectChoices = null;
    }

    public function testChoicesOptionExpectsArrayOrTraversable()
    {
        $this->expectException('Symfony\Component\OptionsResolver\Exception\InvalidOptionsException');
        $this->factory->create(static::TESTED_TYPE, null, [
            'choices' => new \stdClass(),
        ]);
    }

    public function testChoiceLoaderOptionExpectsChoiceLoaderInterface()
    {
        $this->expectException('Symfony\Component\OptionsResolver\Exception\InvalidOptionsException');
        $this->factory->create(static::TESTED_TYPE, null, [
            'choice_loader' => new \stdClass(),
        ]);
    }

    public function testChoiceListAndChoicesCanBeEmpty()
    {
        $this->assertInstanceOf('Symfony\Component\Form\FormInterface', $this->factory->create(static::TESTED_TYPE, null, []));
    }

    public function testExpandedChoicesOptionsTurnIntoChildren()
    {
        $form = $this->factory->create(static::TESTED_TYPE, null, [
            'expanded' => true,
            'choices' => $this->choices,
        ]);

        $this->assertCount(\count($this->choices), $form, 'Each choice should become a new field');
    }

    public function testChoiceListWithScalarValues()
    {
        $view = $this->factory->create(static::TESTED_TYPE, null, [
            'choices' => $this->scalarChoices,
        ])->createView();

        $this->assertSame('1', $view->vars['choices'][0]->value);
        $this->assertSame('0', $view->vars['choices'][1]->value);
        $this->assertSame('', $view->vars['choices'][2]->value);
        $this->assertFalse($view->vars['is_selected']($view->vars['choices'][0], $view->vars['value']), 'True value should not be pre selected');
        $this->assertFalse($view->vars['is_selected']($view->vars['choices'][1], $view->vars['value']), 'False value should not be pre selected');
        $this->assertFalse($view->vars['is_selected']($view->vars['choices'][2], $view->vars['value']), 'Empty value should not be pre selected');
    }

    public function testChoiceListWithScalarValuesAndFalseAsPreSetData()
    {
        $view = $this->factory->create(static::TESTED_TYPE, false, [
            'choices' => $this->scalarChoices,
        ])->createView();

        $this->assertTrue($view->vars['is_selected']($view->vars['choices'][1]->value, $view->vars['value']), 'False value should be pre selected');
    }

    public function testExpandedChoiceListWithScalarValues()
    {
        $view = $this->factory->create(static::TESTED_TYPE, null, [
            'choices' => $this->scalarChoices,
            'expanded' => true,
        ])->createView();

        $this->assertFalse($view->children[0]->vars['checked'], 'True value should not be pre selected');
        $this->assertFalse($view->children[1]->vars['checked'], 'False value should not be pre selected');
        $this->assertTrue($view->children[2]->vars['checked'], 'Empty value should be pre selected');
    }

    public function testExpandedChoiceListWithBooleanAndNullValues()
    {
        $view = $this->factory->create(static::TESTED_TYPE, null, [
            'choices' => $this->booleanChoicesWithNull,
            'expanded' => true,
        ])->createView();

        $this->assertFalse($view->children[0]->vars['checked'], 'True value should not be pre selected');
        $this->assertFalse($view->children[1]->vars['checked'], 'False value should not be pre selected');
        $this->assertTrue($view->children[2]->vars['checked'], 'Empty value should be pre selected');
    }

    public function testExpandedChoiceListWithScalarValuesAndFalseAsPreSetData()
    {
        $view = $this->factory->create(static::TESTED_TYPE, false, [
            'choices' => $this->scalarChoices,
            'expanded' => true,
        ])->createView();

        $this->assertSame('1', $view->vars['choices'][0]->value);
        $this->assertSame('0', $view->vars['choices'][1]->value);
        $this->assertTrue($view->children[1]->vars['checked'], 'False value should be pre selected');
        $this->assertFalse($view->children[2]->vars['checked'], 'Empty value should not be pre selected');
    }

    public function testExpandedChoiceListWithBooleanAndNullValuesAndFalseAsPreSetData()
    {
        $view = $this->factory->create(static::TESTED_TYPE, false, [
            'choices' => $this->booleanChoicesWithNull,
            'expanded' => true,
        ])->createView();

        $this->assertFalse($view->children[0]->vars['checked'], 'True value should not be pre selected');
        $this->assertTrue($view->children[1]->vars['checked'], 'False value should be pre selected');
        $this->assertFalse($view->children[2]->vars['checked'], 'Null value should not be pre selected');
    }

    public function testPlaceholderPresentOnNonRequiredExpandedSingleChoice()
    {
        $form = $this->factory->create(static::TESTED_TYPE, null, [
            'multiple' => false,
            'expanded' => true,
            'required' => false,
            'choices' => $this->choices,
        ]);

        $this->assertArrayHasKey('placeholder', $form);
        $this->assertCount(\count($this->choices) + 1, $form, 'Each choice should become a new field');
    }

    public function testPlaceholderNotPresentIfRequired()
    {
        $form = $this->factory->create(static::TESTED_TYPE, null, [
            'multiple' => false,
            'expanded' => true,
            'required' => true,
            'choices' => $this->choices,
        ]);

        $this->assertArrayNotHasKey('placeholder', $form);
        $this->assertCount(\count($this->choices), $form, 'Each choice should become a new field');
    }

    public function testPlaceholderNotPresentIfMultiple()
    {
        $form = $this->factory->create(static::TESTED_TYPE, null, [
            'multiple' => true,
            'expanded' => true,
            'required' => false,
            'choices' => $this->choices,
        ]);

        $this->assertArrayNotHasKey('placeholder', $form);
        $this->assertCount(\count($this->choices), $form, 'Each choice should become a new field');
    }

    public function testPlaceholderNotPresentIfEmptyChoice()
    {
        $form = $this->factory->create(static::TESTED_TYPE, null, [
            'multiple' => false,
            'expanded' => true,
            'required' => false,
            'choices' => [
                'Empty' => '',
                'Not empty' => 1,
            ],
        ]);

        $this->assertArrayNotHasKey('placeholder', $form);
        $this->assertCount(2, $form, 'Each choice should become a new field');
    }

    public function testPlaceholderWithBooleanChoices()
    {
        $view = $this->factory->create(static::TESTED_TYPE, null, [
            'multiple' => false,
            'expanded' => false,
            'required' => false,
            'choices' => [
                'Yes' => true,
                'No' => false,
            ],
            'placeholder' => 'Select an option',
        ])
            ->createView();

        $this->assertSame('', $view->vars['value'], 'Value should be empty');
        $this->assertSame('1', $view->vars['choices'][0]->value);
        $this->assertSame('0', $view->vars['choices'][1]->value, 'Choice "false" should have "0" as value');
        $this->assertFalse($view->vars['is_selected']($view->vars['choices'][1]->value, $view->vars['value']), 'Choice "false" should not be selected');
    }

    public function testPlaceholderWithBooleanChoicesWithFalseAsPreSetData()
    {
        $view = $this->factory->create(static::TESTED_TYPE, false, [
            'multiple' => false,
            'expanded' => false,
            'required' => false,
            'choices' => [
                'Yes' => true,
                'No' => false,
            ],
            'placeholder' => 'Select an option',
        ])
            ->createView();

        $this->assertSame('0', $view->vars['value'], 'Value should be "0"');
        $this->assertSame('1', $view->vars['choices'][0]->value);
        $this->assertSame('0', $view->vars['choices'][1]->value, 'Choice "false" should have "0" as value');
        $this->assertTrue($view->vars['is_selected']($view->vars['choices'][1]->value, $view->vars['value']), 'Choice "false" should be selected');
    }

    public function testPlaceholderWithExpandedBooleanChoices()
    {
        $form = $this->factory->create(static::TESTED_TYPE, null, [
            'multiple' => false,
            'expanded' => true,
            'required' => false,
            'choices' => [
                'Yes' => true,
                'No' => false,
            ],
            'placeholder' => 'Select an option',
        ]);

        $this->assertArrayHasKey('placeholder', $form, 'Placeholder should be set');
        $this->assertCount(3, $form, 'Each choice should become a new field, placeholder included');

        $view = $form->createView();

        $this->assertSame('', $view->vars['value'], 'Value should be an empty string');
        $this->assertSame('1', $view->vars['choices'][0]->value);
        $this->assertSame('0', $view->vars['choices'][1]->value, 'Choice "false" should have "0" as value');
        $this->assertFalse($view->children[1]->vars['checked'], 'Choice "false" should not be selected');
    }

    public function testPlaceholderWithExpandedBooleanChoicesAndWithFalseAsPreSetData()
    {
        $form = $this->factory->create(static::TESTED_TYPE, false, [
            'multiple' => false,
            'expanded' => true,
            'required' => false,
            'choices' => [
                'Yes' => true,
                'No' => false,
            ],
            'placeholder' => 'Select an option',
        ]);

        $this->assertArrayHasKey('placeholder', $form, 'Placeholder should be set');
        $this->assertCount(3, $form, 'Each choice should become a new field, placeholder included');

        $view = $form->createView();

        $this->assertSame('0', $view->vars['value'], 'Value should be "0"');
        $this->assertSame('1', $view->vars['choices'][0]->value);
        $this->assertSame('0', $view->vars['choices'][1]->value, 'Choice "false" should have "0" as value');
        $this->assertTrue($view->children[1]->vars['checked'], 'Choice "false" should be selected');
    }

    public function testExpandedChoicesOptionsAreFlattened()
    {
        $form = $this->factory->create(static::TESTED_TYPE, null, [
            'expanded' => true,
            'choices' => $this->groupedChoices,
        ]);

        $flattened = [];
        foreach ($this->groupedChoices as $choices) {
            $flattened = array_merge($flattened, array_keys($choices));
        }

        $this->assertCount($form->count(), $flattened, 'Each nested choice should become a new field, not the groups');

        foreach ($flattened as $value => $choice) {
            $this->assertTrue($form->has($value), 'Flattened choice is named after it\'s value');
        }
    }

    public function testExpandedChoicesOptionsAreFlattenedObjectChoices()
    {
        $obj1 = (object) ['id' => 1, 'name' => 'Bernhard'];
        $obj2 = (object) ['id' => 2, 'name' => 'Fabien'];
        $obj3 = (object) ['id' => 3, 'name' => 'Kris'];
        $obj4 = (object) ['id' => 4, 'name' => 'Jon'];
        $obj5 = (object) ['id' => 5, 'name' => 'Roman'];

        $form = $this->factory->create(static::TESTED_TYPE, null, [
            'expanded' => true,
            'choices' => [
                'Symfony' => [$obj1, $obj2, $obj3],
                'Doctrine' => [$obj4, $obj5],
            ],
            'choice_name' => 'id',
        ]);

        $this->assertSame(5, $form->count(), 'Each nested choice should become a new field, not the groups');
        $this->assertTrue($form->has(1));
        $this->assertTrue($form->has(2));
        $this->assertTrue($form->has(3));
        $this->assertTrue($form->has(4));
        $this->assertTrue($form->has(5));
    }

    public function testExpandedCheckboxesAreNeverRequired()
    {
        $form = $this->factory->create(static::TESTED_TYPE, null, [
            'multiple' => true,
            'expanded' => true,
            'required' => true,
            'choices' => $this->choices,
        ]);

        foreach ($form as $child) {
            $this->assertFalse($child->isRequired());
        }
    }

    public function testExpandedRadiosAreRequiredIfChoiceChildIsRequired()
    {
        $form = $this->factory->create(static::TESTED_TYPE, null, [
            'multiple' => false,
            'expanded' => true,
            'required' => true,
            'choices' => $this->choices,
        ]);

        foreach ($form as $child) {
            $this->assertTrue($child->isRequired());
        }
    }

    public function testExpandedRadiosAreNotRequiredIfChoiceChildIsNotRequired()
    {
        $form = $this->factory->create(static::TESTED_TYPE, null, [
            'multiple' => false,
            'expanded' => true,
            'required' => false,
            'choices' => $this->choices,
        ]);

        foreach ($form as $child) {
            $this->assertFalse($child->isRequired());
        }
    }

    public function testSubmitSingleNonExpanded()
    {
        $form = $this->factory->create(static::TESTED_TYPE, null, [
            'multiple' => false,
            'expanded' => false,
            'choices' => $this->choices,
        ]);

        $form->submit('b');

        $this->assertEquals('b', $form->getData());
        $this->assertEquals('b', $form->getViewData());
        $this->assertTrue($form->isSynchronized());
    }

    public function testSubmitSingleNonExpandedInvalidChoice()
    {
        $form = $this->factory->create(static::TESTED_TYPE, null, [
            'multiple' => false,
            'expanded' => false,
            'choices' => $this->choices,
        ]);

        $form->submit('foobar');

        $this->assertNull($form->getData());
        $this->assertEquals('foobar', $form->getViewData());
        $this->assertFalse($form->isSynchronized());
    }

    public function testSubmitSingleNonExpandedNull()
    {
        $form = $this->factory->create(static::TESTED_TYPE, null, [
            'multiple' => false,
            'expanded' => false,
            'choices' => $this->choices,
        ]);

        $form->submit(null);

        $this->assertNull($form->getData());
        $this->assertSame('', $form->getViewData());
        $this->assertTrue($form->isSynchronized());
    }

    // In edge cases (for example, when choices are loaded dynamically by a
    // loader), the choices may be empty. Make sure to behave the same as when
    // choices are available.
    public function testSubmitNull($expected = null, $norm = null, $view = null)
    {
        $form = $this->factory->create(static::TESTED_TYPE, null, [
            'multiple' => false,
            'expanded' => false,
            'choices' => [],
        ]);

        $form->submit(null);

        $this->assertNull($form->getData());
        $this->assertNull($form->getNormData());
        $this->assertSame('', $form->getViewData());
        $this->assertTrue($form->isSynchronized());
    }

    public function testSubmitSingleNonExpandedEmpty()
    {
        $form = $this->factory->create(static::TESTED_TYPE, null, [
            'multiple' => false,
            'expanded' => false,
            'choices' => $this->choices,
        ]);

        $form->submit('');

        $this->assertNull($form->getData());
        $this->assertSame('', $form->getViewData());
        $this->assertTrue($form->isSynchronized());
    }

    public function testSubmitSingleNonExpandedEmptyExplicitEmptyChoice()
    {
        $form = $this->factory->create(static::TESTED_TYPE, null, [
            'multiple' => false,
            'expanded' => false,
            'choices' => [
                'Empty' => 'EMPTY_CHOICE',
            ],
            'choice_value' => function () {
                return '';
            },
        ]);

        $form->submit('');

        $this->assertSame('EMPTY_CHOICE', $form->getData());
        $this->assertSame('', $form->getViewData());
        $this->assertTrue($form->isSynchronized());
    }

    // In edge cases (for example, when choices are loaded dynamically by a
    // loader), the choices may be empty. Make sure to behave the same as when
    // choices are available.
    public function testSubmitSingleNonExpandedEmptyNoChoices()
    {
        $form = $this->factory->create(static::TESTED_TYPE, null, [
            'multiple' => false,
            'expanded' => false,
            'choices' => [],
        ]);

        $form->submit('');

        $this->assertNull($form->getData());
        $this->assertSame('', $form->getViewData());
        $this->assertTrue($form->isSynchronized());
    }

    public function testSubmitSingleNonExpandedFalse()
    {
        $form = $this->factory->create(static::TESTED_TYPE, null, [
            'multiple' => false,
            'expanded' => false,
            'choices' => $this->choices,
        ]);

        $form->submit(false);

        $this->assertNull($form->getData());
        $this->assertSame('', $form->getViewData());
        $this->assertTrue($form->isSynchronized());
    }

    // In edge cases (for example, when choices are loaded dynamically by a
    // loader), the choices may be empty. Make sure to behave the same as when
    // choices are available.
    public function testSubmitSingleNonExpandedFalseNoChoices()
    {
        $form = $this->factory->create(static::TESTED_TYPE, null, [
            'multiple' => false,
            'expanded' => false,
            'choices' => [],
        ]);

        $form->submit(false);

        $this->assertNull($form->getData());
        $this->assertSame('', $form->getViewData());
        $this->assertTrue($form->isSynchronized());
    }

    public function testSubmitSingleNonExpandedObjectChoices()
    {
        $form = $this->factory->create(static::TESTED_TYPE, null, [
            'multiple' => false,
            'expanded' => false,
            'choices' => $this->objectChoices,
            'choice_label' => 'name',
            'choice_value' => 'id',
        ]);

        // "id" value of the second entry
        $form->submit('2');

        $this->assertEquals($this->objectChoices[1], $form->getData());
        $this->assertEquals('2', $form->getViewData());
        $this->assertTrue($form->isSynchronized());
    }

    public function testSubmitNullUsesDefaultEmptyData($emptyData = 'empty', $expectedData = null)
    {
        $form = $this->factory->create(static::TESTED_TYPE, null, [
            'multiple' => false,
            'expanded' => false,
            // empty data must match string choice value
            'choices' => [$emptyData],
            'empty_data' => $emptyData,
        ]);

        $form->submit(null);

        $this->assertSame($emptyData, $form->getData());
    }

    public function testSubmitSingleChoiceWithEmptyDataAndInitialData()
    {
        $form = $this->factory->create(static::TESTED_TYPE, 'initial', [
            'multiple' => false,
            'expanded' => false,
            'choices' => ['initial', 'test'],
            'empty_data' => 'test',
        ]);

        $form->submit(null);

        $this->assertSame('test', $form->getData());
    }

    public function testSubmitMultipleChoiceWithEmptyData()
    {
        $form = $this->factory->create(static::TESTED_TYPE, null, [
            'multiple' => true,
            'expanded' => false,
            'choices' => ['test'],
            'empty_data' => ['test'],
        ]);

        $form->submit(null);

        $this->assertSame(['test'], $form->getData());
    }

    public function testSubmitMultipleChoiceWithEmptyDataAndInitialEmptyArray()
    {
        $form = $this->factory->create(static::TESTED_TYPE, [], [
            'multiple' => true,
            'expanded' => false,
            'choices' => ['test'],
            'empty_data' => ['test'],
        ]);

        $form->submit(null);

        $this->assertSame(['test'], $form->getData());
    }

    public function testSubmitMultipleChoiceWithEmptyDataAndInitialData()
    {
        $form = $this->factory->create(static::TESTED_TYPE, ['initial'], [
            'multiple' => true,
            'expanded' => false,
            'choices' => ['initial', 'test'],
            'empty_data' => ['test'],
        ]);

        $form->submit(null);

        $this->assertSame(['test'], $form->getData());
    }

    public function testSubmitSingleChoiceExpandedWithEmptyData()
    {
        $form = $this->factory->create(static::TESTED_TYPE, null, [
            'multiple' => false,
            'expanded' => true,
            'choices' => ['test'],
            'empty_data' => 'test',
        ]);

        $form->submit(null);

        $this->assertSame('test', $form->getData());
    }

    public function testSubmitSingleChoiceExpandedWithEmptyDataAndInitialData()
    {
        $form = $this->factory->create(static::TESTED_TYPE, 'initial', [
            'multiple' => false,
            'expanded' => true,
            'choices' => ['initial', 'test'],
            'empty_data' => 'test',
        ]);

        $form->submit(null);

        $this->assertSame('test', $form->getData());
    }

    public function testSubmitMultipleChoiceExpandedWithEmptyData()
    {
        $form = $this->factory->create(static::TESTED_TYPE, null, [
            'multiple' => true,
            'expanded' => true,
            'choices' => ['test'],
            'empty_data' => ['test'],
        ]);

        $form->submit(null);

        $this->assertSame(['test'], $form->getData());
    }

    public function testSubmitMultipleChoiceExpandedWithEmptyDataAndInitialEmptyArray()
    {
        $form = $this->factory->create(static::TESTED_TYPE, [], [
            'multiple' => true,
            'expanded' => true,
            'choices' => ['test'],
            'empty_data' => ['test'],
        ]);

        $form->submit(null);

        $this->assertSame(['test'], $form->getData());
    }

    public function testSubmitMultipleChoiceExpandedWithEmptyDataAndInitialData()
    {
        $form = $this->factory->create(static::TESTED_TYPE, ['init'], [
            'multiple' => true,
            'expanded' => true,
            'choices' => ['init', 'test'],
            'empty_data' => ['test'],
        ]);

        $form->submit(null);

        $this->assertSame(['test'], $form->getData());
    }

    public function testNullChoices()
    {
        $form = $this->factory->create(static::TESTED_TYPE, null, [
            'multiple' => false,
            'expanded' => false,
            'choices' => null,
        ]);
        $this->assertNull($form->getConfig()->getOption('choices'));
        $this->assertFalse($form->getConfig()->getOption('multiple'));
        $this->assertFalse($form->getConfig()->getOption('expanded'));
    }

    public function testSubmitMultipleNonExpanded()
    {
        $form = $this->factory->create(static::TESTED_TYPE, null, [
            'multiple' => true,
            'expanded' => false,
            'choices' => $this->choices,
        ]);

        $form->submit(['a', 'b']);

        $this->assertEquals(['a', 'b'], $form->getData());
        $this->assertEquals(['a', 'b'], $form->getViewData());
        $this->assertTrue($form->isSynchronized());
    }

    public function testSubmitMultipleNonExpandedEmpty()
    {
        $form = $this->factory->create(static::TESTED_TYPE, null, [
            'multiple' => true,
            'expanded' => false,
            'choices' => $this->choices,
        ]);

        $form->submit([]);

        $this->assertSame([], $form->getData());
        $this->assertSame([], $form->getViewData());
        $this->assertTrue($form->isSynchronized());
    }

    // In edge cases (for example, when choices are loaded dynamically by a
    // loader), the choices may be empty. Make sure to behave the same as when
    // choices are available.
    public function testSubmitMultipleNonExpandedEmptyNoChoices()
    {
        $form = $this->factory->create(static::TESTED_TYPE, null, [
            'multiple' => true,
            'expanded' => false,
            'choices' => [],
        ]);

        $form->submit([]);

        $this->assertSame([], $form->getData());
        $this->assertSame([], $form->getViewData());
        $this->assertTrue($form->isSynchronized());
    }

    public function testSubmitMultipleNonExpandedInvalidScalarChoice()
    {
        $form = $this->factory->create(static::TESTED_TYPE, null, [
            'multiple' => true,
            'expanded' => false,
            'choices' => $this->choices,
        ]);

        $form->submit('foobar');

        $this->assertNull($form->getData());
        $this->assertEquals('foobar', $form->getViewData());
        $this->assertFalse($form->isSynchronized());
    }

    public function testSubmitMultipleNonExpandedInvalidArrayChoice()
    {
        $form = $this->factory->create(static::TESTED_TYPE, null, [
            'multiple' => true,
            'expanded' => false,
            'choices' => $this->choices,
        ]);

        $form->submit(['a', 'foobar']);

        $this->assertNull($form->getData());
        $this->assertEquals(['a', 'foobar'], $form->getViewData());
        $this->assertFalse($form->isSynchronized());
    }

    public function testSubmitMultipleNonExpandedObjectChoices()
    {
        $form = $this->factory->create(static::TESTED_TYPE, null, [
            'multiple' => true,
            'expanded' => false,
            'choices' => $this->objectChoices,
            'choice_label' => 'name',
            'choice_value' => 'id',
        ]);

        $form->submit(['2', '3']);

        $this->assertEquals([$this->objectChoices[1], $this->objectChoices[2]], $form->getData());
        $this->assertEquals(['2', '3'], $form->getViewData());
        $this->assertTrue($form->isSynchronized());
    }

    public function testSubmitSingleExpandedRequired()
    {
        $form = $this->factory->create(static::TESTED_TYPE, null, [
            'multiple' => false,
            'expanded' => true,
            'required' => true,
            'choices' => $this->choices,
        ]);

        $form->submit('b');

        $this->assertSame('b', $form->getData());
        $this->assertSame('b', $form->getViewData());
        $this->assertEmpty($form->getExtraData());
        $this->assertTrue($form->isSynchronized());

        $this->assertFalse($form[0]->getData());
        $this->assertTrue($form[1]->getData());
        $this->assertFalse($form[2]->getData());
        $this->assertFalse($form[3]->getData());
        $this->assertFalse($form[4]->getData());
        $this->assertNull($form[0]->getViewData());
        $this->assertSame('b', $form[1]->getViewData());
        $this->assertNull($form[2]->getViewData());
        $this->assertNull($form[3]->getViewData());
        $this->assertNull($form[4]->getViewData());
    }

    public function testSubmitSingleExpandedRequiredInvalidChoice()
    {
        $form = $this->factory->create(static::TESTED_TYPE, null, [
            'multiple' => false,
            'expanded' => true,
            'required' => true,
            'choices' => $this->choices,
        ]);

        $form->submit('foobar');

        $this->assertNull($form->getData());
        $this->assertSame('foobar', $form->getViewData());
        $this->assertEmpty($form->getExtraData());
        $this->assertFalse($form->isSynchronized());

        $this->assertFalse($form[0]->getData());
        $this->assertFalse($form[1]->getData());
        $this->assertFalse($form[2]->getData());
        $this->assertFalse($form[3]->getData());
        $this->assertFalse($form[4]->getData());
        $this->assertNull($form[0]->getViewData());
        $this->assertNull($form[1]->getViewData());
        $this->assertNull($form[2]->getViewData());
        $this->assertNull($form[3]->getViewData());
        $this->assertNull($form[4]->getViewData());
    }

    public function testSubmitSingleExpandedNonRequired()
    {
        $form = $this->factory->create(static::TESTED_TYPE, null, [
            'multiple' => false,
            'expanded' => true,
            'required' => false,
            'choices' => $this->choices,
        ]);

        $form->submit('b');

        $this->assertSame('b', $form->getData());
        $this->assertSame('b', $form->getViewData());
        $this->assertEmpty($form->getExtraData());
        $this->assertTrue($form->isSynchronized());

        $this->assertFalse($form['placeholder']->getData());
        $this->assertFalse($form[0]->getData());
        $this->assertTrue($form[1]->getData());
        $this->assertFalse($form[2]->getData());
        $this->assertFalse($form[3]->getData());
        $this->assertFalse($form[4]->getData());
        $this->assertNull($form['placeholder']->getViewData());
        $this->assertNull($form[0]->getViewData());
        $this->assertSame('b', $form[1]->getViewData());
        $this->assertNull($form[2]->getViewData());
        $this->assertNull($form[3]->getViewData());
        $this->assertNull($form[4]->getViewData());
    }

    public function testSubmitSingleExpandedNonRequiredInvalidChoice()
    {
        $form = $this->factory->create(static::TESTED_TYPE, null, [
            'multiple' => false,
            'expanded' => true,
            'required' => false,
            'choices' => $this->choices,
        ]);

        $form->submit('foobar');

        $this->assertNull($form->getData());
        $this->assertSame('foobar', $form->getViewData());
        $this->assertEmpty($form->getExtraData());
        $this->assertFalse($form->isSynchronized());

        $this->assertFalse($form[0]->getData());
        $this->assertFalse($form[1]->getData());
        $this->assertFalse($form[2]->getData());
        $this->assertFalse($form[3]->getData());
        $this->assertFalse($form[4]->getData());
        $this->assertNull($form[0]->getViewData());
        $this->assertNull($form[1]->getViewData());
        $this->assertNull($form[2]->getViewData());
        $this->assertNull($form[3]->getViewData());
        $this->assertNull($form[4]->getViewData());
    }

    public function testSubmitSingleExpandedRequiredNull()
    {
        $form = $this->factory->create(static::TESTED_TYPE, null, [
            'multiple' => false,
            'expanded' => true,
            'required' => true,
            'choices' => $this->choices,
        ]);

        $form->submit(null);

        $this->assertNull($form->getData());
        $this->assertSame('', $form->getViewData(), 'View data should always be a string');
        $this->assertSame([], $form->getExtraData(), 'ChoiceType is compound when expanded, extra data should always be an array');
        $this->assertTrue($form->isSynchronized());

        $this->assertFalse($form[0]->getData());
        $this->assertFalse($form[1]->getData());
        $this->assertFalse($form[2]->getData());
        $this->assertFalse($form[3]->getData());
        $this->assertFalse($form[4]->getData());
        $this->assertNull($form[0]->getViewData());
        $this->assertNull($form[1]->getViewData());
        $this->assertNull($form[2]->getViewData());
        $this->assertNull($form[3]->getViewData());
        $this->assertNull($form[4]->getViewData());
    }

    // In edge cases (for example, when choices are loaded dynamically by a
    // loader), the choices may be empty. Make sure to behave the same as when
    // choices are available.
    public function testSubmitSingleExpandedRequiredNullNoChoices()
    {
        $form = $this->factory->create(static::TESTED_TYPE, null, [
            'multiple' => false,
            'expanded' => true,
            'required' => true,
            'choices' => [],
        ]);

        $form->submit(null);

        $this->assertNull($form->getData());
        $this->assertSame('', $form->getViewData(), 'View data should always be a string');
        $this->assertSame([], $form->getExtraData(), 'ChoiceType is compound when expanded, extra data should always be an array');
        $this->assertTrue($form->isSynchronized());
    }

    public function testSubmitSingleExpandedRequiredEmpty()
    {
        $form = $this->factory->create(static::TESTED_TYPE, null, [
            'multiple' => false,
            'expanded' => true,
            'required' => true,
            'choices' => $this->choices,
        ]);

        $form->submit('');

        $this->assertNull($form->getData());
        $this->assertSame('', $form->getViewData(), 'View data should always be a string');
        $this->assertSame([], $form->getExtraData(), 'ChoiceType is compound when expanded, extra data should always be an array');
        $this->assertTrue($form->isSynchronized());

        $this->assertFalse($form[0]->getData());
        $this->assertFalse($form[1]->getData());
        $this->assertFalse($form[2]->getData());
        $this->assertFalse($form[3]->getData());
        $this->assertFalse($form[4]->getData());
        $this->assertNull($form[0]->getViewData());
        $this->assertNull($form[1]->getViewData());
        $this->assertNull($form[2]->getViewData());
        $this->assertNull($form[3]->getViewData());
        $this->assertNull($form[4]->getViewData());
    }

    // In edge cases (for example, when choices are loaded dynamically by a
    // loader), the choices may be empty. Make sure to behave the same as when
    // choices are available.
    public function testSubmitSingleExpandedRequiredEmptyNoChoices()
    {
        $form = $this->factory->create(static::TESTED_TYPE, null, [
            'multiple' => false,
            'expanded' => true,
            'required' => true,
            'choices' => [],
        ]);

        $form->submit('');

        $this->assertNull($form->getData());
        $this->assertSame('', $form->getViewData(), 'View data should always be a string');
        $this->assertSame([], $form->getExtraData(), 'ChoiceType is compound when expanded, extra data should always be an array');
        $this->assertTrue($form->isSynchronized());
    }

    public function testSubmitSingleExpandedRequiredFalse()
    {
        $form = $this->factory->create(static::TESTED_TYPE, null, [
            'multiple' => false,
            'expanded' => true,
            'required' => true,
            'choices' => $this->choices,
        ]);

        $form->submit(false);

        $this->assertNull($form->getData());
        $this->assertSame('', $form->getViewData(), 'View data should always be a string');
        $this->assertSame([], $form->getExtraData(), 'ChoiceType is compound when expanded, extra data should always be an array');
        $this->assertTrue($form->isSynchronized());

        $this->assertFalse($form[0]->getData());
        $this->assertFalse($form[1]->getData());
        $this->assertFalse($form[2]->getData());
        $this->assertFalse($form[3]->getData());
        $this->assertFalse($form[4]->getData());
        $this->assertNull($form[0]->getViewData());
        $this->assertNull($form[1]->getViewData());
        $this->assertNull($form[2]->getViewData());
        $this->assertNull($form[3]->getViewData());
        $this->assertNull($form[4]->getViewData());
    }

    // In edge cases (for example, when choices are loaded dynamically by a
    // loader), the choices may be empty. Make sure to behave the same as when
    // choices are available.
    public function testSubmitSingleExpandedRequiredFalseNoChoices()
    {
        $form = $this->factory->create(static::TESTED_TYPE, null, [
            'multiple' => false,
            'expanded' => true,
            'required' => true,
            'choices' => [],
        ]);

        $form->submit(false);

        $this->assertNull($form->getData());
        $this->assertSame('', $form->getViewData(), 'View data should always be a string');
        $this->assertSame([], $form->getExtraData(), 'ChoiceType is compound when expanded, extra data should always be an array');
        $this->assertTrue($form->isSynchronized());
    }

    public function testSubmitSingleExpandedNonRequiredNull()
    {
        $form = $this->factory->create(static::TESTED_TYPE, null, [
            'multiple' => false,
            'expanded' => true,
            'required' => false,
            'choices' => $this->choices,
        ]);

        $form->submit(null);

        $this->assertNull($form->getData());
        $this->assertSame('', $form->getViewData(), 'View data should always be a string');
        $this->assertSame([], $form->getExtraData(), 'ChoiceType is compound when expanded, extra data should always be an array');
        $this->assertTrue($form->isSynchronized());

        $this->assertTrue($form['placeholder']->getData());
        $this->assertFalse($form[0]->getData());
        $this->assertFalse($form[1]->getData());
        $this->assertFalse($form[2]->getData());
        $this->assertFalse($form[3]->getData());
        $this->assertFalse($form[4]->getData());
        $this->assertSame('', $form['placeholder']->getViewData());
        $this->assertNull($form[0]->getViewData());
        $this->assertNull($form[1]->getViewData());
        $this->assertNull($form[2]->getViewData());
        $this->assertNull($form[3]->getViewData());
        $this->assertNull($form[4]->getViewData());
    }

    // In edge cases (for example, when choices are loaded dynamically by a
    // loader), the choices may be empty. Make sure to behave the same as when
    // choices are available.
    public function testSubmitSingleExpandedNonRequiredNullNoChoices()
    {
        $form = $this->factory->create(static::TESTED_TYPE, null, [
            'multiple' => false,
            'expanded' => true,
            'required' => false,
            'choices' => [],
        ]);

        $form->submit(null);

        $this->assertNull($form->getData());
        $this->assertSame('', $form->getViewData(), 'View data should always be a string');
        $this->assertSame([], $form->getExtraData(), 'ChoiceType is compound when expanded, extra data should always be an array');
        $this->assertTrue($form->isSynchronized());
    }

    public function testSubmitSingleExpandedNonRequiredEmpty()
    {
        $form = $this->factory->create(static::TESTED_TYPE, null, [
            'multiple' => false,
            'expanded' => true,
            'required' => false,
            'choices' => $this->choices,
        ]);

        $form->submit('');

        $this->assertNull($form->getData());
        $this->assertSame('', $form->getViewData(), 'View data should always be a string');
        $this->assertSame([], $form->getExtraData(), 'ChoiceType is compound when expanded, extra data should always be an array');
        $this->assertTrue($form->isSynchronized());

        $this->assertTrue($form['placeholder']->getData());
        $this->assertFalse($form[0]->getData());
        $this->assertFalse($form[1]->getData());
        $this->assertFalse($form[2]->getData());
        $this->assertFalse($form[3]->getData());
        $this->assertFalse($form[4]->getData());
        $this->assertSame('', $form['placeholder']->getViewData());
        $this->assertNull($form[0]->getViewData());
        $this->assertNull($form[1]->getViewData());
        $this->assertNull($form[2]->getViewData());
        $this->assertNull($form[3]->getViewData());
        $this->assertNull($form[4]->getViewData());
    }

    // In edge cases (for example, when choices are loaded dynamically by a
    // loader), the choices may be empty. Make sure to behave the same as when
    // choices are available.
    public function testSubmitSingleExpandedNonRequiredEmptyNoChoices()
    {
        $form = $this->factory->create(static::TESTED_TYPE, null, [
            'multiple' => false,
            'expanded' => true,
            'required' => false,
            'choices' => [],
        ]);

        $form->submit('');

        $this->assertNull($form->getData());
        $this->assertSame('', $form->getViewData(), 'View data should always be a string');
        $this->assertSame([], $form->getExtraData(), 'ChoiceType is compound when expanded, extra data should always be an array');
        $this->assertTrue($form->isSynchronized());
    }

    public function testSubmitSingleExpandedNonRequiredFalse()
    {
        $form = $this->factory->create(static::TESTED_TYPE, null, [
            'multiple' => false,
            'expanded' => true,
            'required' => false,
            'choices' => $this->choices,
        ]);

        $form->submit(false);

        $this->assertNull($form->getData());
        $this->assertSame('', $form->getViewData(), 'View data should always be a string');
        $this->assertSame([], $form->getExtraData(), 'ChoiceType is compound when expanded, extra data should always be an array');
        $this->assertTrue($form->isSynchronized());

        $this->assertTrue($form['placeholder']->getData());
        $this->assertFalse($form[0]->getData());
        $this->assertFalse($form[1]->getData());
        $this->assertFalse($form[2]->getData());
        $this->assertFalse($form[3]->getData());
        $this->assertFalse($form[4]->getData());
        $this->assertSame('', $form['placeholder']->getViewData());
        $this->assertNull($form[0]->getViewData());
        $this->assertNull($form[1]->getViewData());
        $this->assertNull($form[2]->getViewData());
        $this->assertNull($form[3]->getViewData());
        $this->assertNull($form[4]->getViewData());
    }

    // In edge cases (for example, when choices are loaded dynamically by a
    // loader), the choices may be empty. Make sure to behave the same as when
    // choices are available.
    public function testSubmitSingleExpandedNonRequiredFalseNoChoices()
    {
        $form = $this->factory->create(static::TESTED_TYPE, null, [
            'multiple' => false,
            'expanded' => true,
            'required' => false,
            'choices' => [],
        ]);

        $form->submit(false);

        $this->assertNull($form->getData());
        $this->assertSame('', $form->getViewData(), 'View data should always be a string');
        $this->assertSame([], $form->getExtraData(), 'ChoiceType is compound when expanded, extra data should always be an array');
        $this->assertTrue($form->isSynchronized());
    }

    public function testSubmitSingleExpandedWithEmptyChild()
    {
        $form = $this->factory->create(static::TESTED_TYPE, null, [
            'multiple' => false,
            'expanded' => true,
            'choices' => [
                'Empty' => '',
                'Not empty' => 1,
            ],
        ]);

        $form->submit('');

        $this->assertSame('', $form->getData());
        $this->assertTrue($form->isSynchronized());

        $this->assertTrue($form[0]->getData());
        $this->assertFalse($form[1]->getData());
        $this->assertSame('', $form[0]->getViewData());
        $this->assertNull($form[1]->getViewData());
    }

    public function testSubmitSingleExpandedObjectChoices()
    {
        $form = $this->factory->create(static::TESTED_TYPE, null, [
            'multiple' => false,
            'expanded' => true,
            'choices' => $this->objectChoices,
            'choice_label' => 'name',
            'choice_value' => 'id',
        ]);

        $form->submit('2');

        $this->assertSame($this->objectChoices[1], $form->getData());
        $this->assertTrue($form->isSynchronized());

        $this->assertFalse($form[0]->getData());
        $this->assertTrue($form[1]->getData());
        $this->assertFalse($form[2]->getData());
        $this->assertFalse($form[3]->getData());
        $this->assertFalse($form[4]->getData());
        $this->assertNull($form[0]->getViewData());
        $this->assertSame('2', $form[1]->getViewData());
        $this->assertNull($form[2]->getViewData());
        $this->assertNull($form[3]->getViewData());
        $this->assertNull($form[4]->getViewData());
    }

    public function testSubmitMultipleExpanded()
    {
        $form = $this->factory->create(static::TESTED_TYPE, null, [
            'multiple' => true,
            'expanded' => true,
            'choices' => $this->choices,
        ]);

        $form->submit(['a', 'c']);

        $this->assertSame(['a', 'c'], $form->getData());
        $this->assertSame(['a', 'c'], $form->getViewData());
        $this->assertEmpty($form->getExtraData());
        $this->assertTrue($form->isSynchronized());

        $this->assertTrue($form[0]->getData());
        $this->assertFalse($form[1]->getData());
        $this->assertTrue($form[2]->getData());
        $this->assertFalse($form[3]->getData());
        $this->assertFalse($form[4]->getData());
        $this->assertSame('a', $form[0]->getViewData());
        $this->assertNull($form[1]->getViewData());
        $this->assertSame('c', $form[2]->getViewData());
        $this->assertNull($form[3]->getViewData());
        $this->assertNull($form[4]->getViewData());
    }

    public function testSubmitMultipleExpandedInvalidScalarChoice()
    {
        $form = $this->factory->create(static::TESTED_TYPE, null, [
            'multiple' => true,
            'expanded' => true,
            'choices' => $this->choices,
        ]);

        $form->submit('foobar');

        $this->assertNull($form->getData());
        $this->assertSame('foobar', $form->getViewData());
        $this->assertEmpty($form->getExtraData());
        $this->assertFalse($form->isSynchronized());

        $this->assertFalse($form[0]->getData());
        $this->assertFalse($form[1]->getData());
        $this->assertFalse($form[2]->getData());
        $this->assertFalse($form[3]->getData());
        $this->assertFalse($form[4]->getData());
        $this->assertNull($form[0]->getViewData());
        $this->assertNull($form[1]->getViewData());
        $this->assertNull($form[2]->getViewData());
        $this->assertNull($form[3]->getViewData());
        $this->assertNull($form[4]->getViewData());
    }

    public function testSubmitMultipleExpandedInvalidArrayChoice()
    {
        $form = $this->factory->create(static::TESTED_TYPE, null, [
            'multiple' => true,
            'expanded' => true,
            'choices' => $this->choices,
        ]);

        $form->submit(['a', 'foobar']);

        $this->assertNull($form->getData());
        $this->assertSame(['a', 'foobar'], $form->getViewData());
        $this->assertEmpty($form->getExtraData());
        $this->assertFalse($form->isSynchronized());

        $this->assertFalse($form[0]->getData());
        $this->assertFalse($form[1]->getData());
        $this->assertFalse($form[2]->getData());
        $this->assertFalse($form[3]->getData());
        $this->assertFalse($form[4]->getData());
        $this->assertNull($form[0]->getViewData());
        $this->assertNull($form[1]->getViewData());
        $this->assertNull($form[2]->getViewData());
        $this->assertNull($form[3]->getViewData());
        $this->assertNull($form[4]->getViewData());
    }

    public function testSubmitMultipleExpandedEmpty()
    {
        $form = $this->factory->create(static::TESTED_TYPE, null, [
            'multiple' => true,
            'expanded' => true,
            'choices' => $this->choices,
        ]);

        $form->submit([]);

        $this->assertSame([], $form->getData());
        $this->assertTrue($form->isSynchronized());

        $this->assertFalse($form[0]->getData());
        $this->assertFalse($form[1]->getData());
        $this->assertFalse($form[2]->getData());
        $this->assertFalse($form[3]->getData());
        $this->assertFalse($form[4]->getData());
        $this->assertNull($form[0]->getViewData());
        $this->assertNull($form[1]->getViewData());
        $this->assertNull($form[2]->getViewData());
        $this->assertNull($form[3]->getViewData());
        $this->assertNull($form[4]->getViewData());
    }

    // In edge cases (for example, when choices are loaded dynamically by a
    // loader), the choices may be empty. Make sure to behave the same as when
    // choices are available.
    public function testSubmitMultipleExpandedEmptyNoChoices()
    {
        $form = $this->factory->create(static::TESTED_TYPE, null, [
            'multiple' => true,
            'expanded' => true,
            'choices' => [],
        ]);

        $form->submit([]);

        $this->assertSame([], $form->getData());
        $this->assertTrue($form->isSynchronized());
    }

    public function testSubmitMultipleExpandedWithEmptyChild()
    {
        $form = $this->factory->create(static::TESTED_TYPE, null, [
            'multiple' => true,
            'expanded' => true,
            'choices' => [
                'Empty' => '',
                'Not Empty' => 1,
                'Not Empty 2' => 2,
            ],
        ]);

        $form->submit(['', '2']);

        $this->assertSame(['', 2], $form->getData());
        $this->assertTrue($form->isSynchronized());

        $this->assertTrue($form[0]->getData());
        $this->assertFalse($form[1]->getData());
        $this->assertTrue($form[2]->getData());
        $this->assertSame('', $form[0]->getViewData());
        $this->assertNull($form[1]->getViewData());
        $this->assertSame('2', $form[2]->getViewData());
    }

    public function testSubmitMultipleExpandedObjectChoices()
    {
        $form = $this->factory->create(static::TESTED_TYPE, null, [
            'multiple' => true,
            'expanded' => true,
            'choices' => $this->objectChoices,
            'choice_label' => 'name',
            'choice_value' => 'id',
        ]);

        $form->submit(['1', '2']);

        $this->assertSame([$this->objectChoices[0], $this->objectChoices[1]], $form->getData());
        $this->assertTrue($form->isSynchronized());

        $this->assertTrue($form[0]->getData());
        $this->assertTrue($form[1]->getData());
        $this->assertFalse($form[2]->getData());
        $this->assertFalse($form[3]->getData());
        $this->assertFalse($form[4]->getData());
        $this->assertSame('1', $form[0]->getViewData());
        $this->assertSame('2', $form[1]->getViewData());
        $this->assertNull($form[2]->getViewData());
        $this->assertNull($form[3]->getViewData());
        $this->assertNull($form[4]->getViewData());
    }

    public function testSubmitMultipleChoicesInts()
    {
        $form = $this->factory->create(static::TESTED_TYPE, null, [
            'multiple' => true,
            'choices' => array_flip($this->numericChoicesFlipped),
        ]);

        $form->submit([1, 2]);

        $this->assertTrue($form->isSynchronized());
    }

    public function testSingleSelectedObjectChoices()
    {
        $view = $this->factory->create(static::TESTED_TYPE, $this->objectChoices[3], [
            'multiple' => false,
            'expanded' => false,
            'choices' => $this->objectChoices,
            'choice_label' => 'name',
            'choice_value' => 'id',
        ])
            ->createView();

        /** @var callable $selectedChecker */
        $selectedChecker = $view->vars['is_selected'];

        $this->assertTrue($selectedChecker($view->vars['choices'][3]->value, $view->vars['value']));
        $this->assertFalse($selectedChecker($view->vars['choices'][1]->value, $view->vars['value']));
    }

    public function testMultipleSelectedObjectChoices()
    {
        $view = $this->factory->create(static::TESTED_TYPE, [$this->objectChoices[3]], [
            'multiple' => true,
            'expanded' => false,
            'choices' => $this->objectChoices,
            'choice_label' => 'name',
            'choice_value' => 'id',
        ])
            ->createView();

        /** @var callable $selectedChecker */
        $selectedChecker = $view->vars['is_selected'];

        $this->assertTrue($selectedChecker($view->vars['choices'][3]->value, $view->vars['value']));
        $this->assertFalse($selectedChecker($view->vars['choices'][1]->value, $view->vars['value']));
    }

    public function testPassRequiredToView()
    {
        $view = $this->factory->create(static::TESTED_TYPE, null, [
            'choices' => $this->choices,
        ])
            ->createView();

        $this->assertTrue($view->vars['required']);
    }

    public function testPassNonRequiredToView()
    {
        $view = $this->factory->create(static::TESTED_TYPE, null, [
            'required' => false,
            'choices' => $this->choices,
        ])
            ->createView();

        $this->assertFalse($view->vars['required']);
    }

    public function testPassMultipleToView()
    {
        $view = $this->factory->create(static::TESTED_TYPE, null, [
            'multiple' => true,
            'choices' => $this->choices,
        ])
            ->createView();

        $this->assertTrue($view->vars['multiple']);
    }

    public function testPassExpandedToView()
    {
        $view = $this->factory->create(static::TESTED_TYPE, null, [
            'expanded' => true,
            'choices' => $this->choices,
        ])
            ->createView();

        $this->assertTrue($view->vars['expanded']);
    }

    public function testPassChoiceTranslationDomainToView()
    {
        $view = $this->factory->create(static::TESTED_TYPE, null, [
            'choices' => $this->choices,
        ])
            ->createView();

        $this->assertNull($view->vars['choice_translation_domain']);
    }

    public function testChoiceTranslationDomainWithTrueValueToView()
    {
        $view = $this->factory->create(static::TESTED_TYPE, null, [
            'choices' => $this->choices,
            'choice_translation_domain' => true,
        ])
            ->createView();

        $this->assertNull($view->vars['choice_translation_domain']);
    }

    public function testDefaultChoiceTranslationDomainIsSameAsTranslationDomainToView()
    {
        $view = $this->factory->create(static::TESTED_TYPE, null, [
            'choices' => $this->choices,
            'translation_domain' => 'foo',
        ])
            ->createView();

        $this->assertEquals('foo', $view->vars['choice_translation_domain']);
    }

    public function testInheritChoiceTranslationDomainFromParent()
    {
        $view = $this->factory
            ->createNamedBuilder('parent', FormTypeTest::TESTED_TYPE, null, [
                'translation_domain' => 'domain',
            ])
            ->add('child', static::TESTED_TYPE)
            ->getForm()
            ->createView();

        $this->assertEquals('domain', $view['child']->vars['choice_translation_domain']);
    }

    public function testPlaceholderIsNullByDefaultIfRequired()
    {
        $view = $this->factory->create(static::TESTED_TYPE, null, [
            'multiple' => false,
            'required' => true,
            'choices' => $this->choices,
        ])
            ->createView();

        $this->assertNull($view->vars['placeholder']);
    }

    public function testPlaceholderIsEmptyStringByDefaultIfNotRequired()
    {
        $view = $this->factory->create(static::TESTED_TYPE, null, [
            'multiple' => false,
            'required' => false,
            'choices' => $this->choices,
        ])
            ->createView();

        $this->assertSame('', $view->vars['placeholder']);
    }

    /**
     * @dataProvider getOptionsWithPlaceholder
     */
    public function testPassPlaceholderToView($multiple, $expanded, $required, $placeholder, $viewValue)
    {
        $view = $this->factory->create(static::TESTED_TYPE, null, [
            'multiple' => $multiple,
            'expanded' => $expanded,
            'required' => $required,
            'placeholder' => $placeholder,
            'choices' => $this->choices,
        ])
            ->createView();

        $this->assertSame($viewValue, $view->vars['placeholder']);
        $this->assertFalse($view->vars['placeholder_in_choices']);
    }

    /**
     * @dataProvider getOptionsWithPlaceholder
     */
    public function testDontPassPlaceholderIfContainedInChoices($multiple, $expanded, $required, $placeholder, $viewValue)
    {
        $view = $this->factory->create(static::TESTED_TYPE, null, [
            'multiple' => $multiple,
            'expanded' => $expanded,
            'required' => $required,
            'placeholder' => $placeholder,
            'choices' => ['Empty' => '', 'A' => 'a'],
        ])
            ->createView();

        $this->assertNull($view->vars['placeholder']);
        $this->assertTrue($view->vars['placeholder_in_choices']);
    }

    public function getOptionsWithPlaceholder()
    {
        return [
            // single non-expanded
            [false, false, false, 'foobar', 'foobar'],
            [false, false, false, '', ''],
            [false, false, false, null, null],
            [false, false, false, false, null],
            [false, false, true, 'foobar', 'foobar'],
            [false, false, true, '', ''],
            [false, false, true, null, null],
            [false, false, true, false, null],
            // single expanded
            [false, true, false, 'foobar', 'foobar'],
            // radios should never have an empty label
            [false, true, false, '', 'None'],
            [false, true, false, null, null],
            [false, true, false, false, null],
            // required radios should never have a placeholder
            [false, true, true, 'foobar', null],
            [false, true, true, '', null],
            [false, true, true, null, null],
            [false, true, true, false, null],
            // multiple non-expanded
            [true, false, false, 'foobar', null],
            [true, false, false, '', null],
            [true, false, false, null, null],
            [true, false, false, false, null],
            [true, false, true, 'foobar', null],
            [true, false, true, '', null],
            [true, false, true, null, null],
            [true, false, true, false, null],
            // multiple expanded
            [true, true, false, 'foobar', null],
            [true, true, false, '', null],
            [true, true, false, null, null],
            [true, true, false, false, null],
            [true, true, true, 'foobar', null],
            [true, true, true, '', null],
            [true, true, true, null, null],
            [true, true, true, false, null],
        ];
    }

    public function testPassChoicesToView()
    {
        $choices = ['A' => 'a', 'B' => 'b', 'C' => 'c', 'D' => 'd'];
        $view = $this->factory->create(static::TESTED_TYPE, null, [
            'choices' => $choices,
        ])
            ->createView();

        $this->assertEquals([
            new ChoiceView('a', 'a', 'A'),
            new ChoiceView('b', 'b', 'B'),
            new ChoiceView('c', 'c', 'C'),
            new ChoiceView('d', 'd', 'D'),
        ], $view->vars['choices']);
    }

    public function testPassPreferredChoicesToView()
    {
        $choices = ['A' => 'a', 'B' => 'b', 'C' => 'c', 'D' => 'd'];
        $view = $this->factory->create(static::TESTED_TYPE, null, [
            'choices' => $choices,
            'preferred_choices' => ['b', 'd'],
        ])
            ->createView();

        $this->assertEquals([
            0 => new ChoiceView('a', 'a', 'A'),
            2 => new ChoiceView('c', 'c', 'C'),
        ], $view->vars['choices']);
        $this->assertEquals([
            1 => new ChoiceView('b', 'b', 'B'),
            3 => new ChoiceView('d', 'd', 'D'),
        ], $view->vars['preferred_choices']);
    }

    public function testPassHierarchicalChoicesToView()
    {
        $view = $this->factory->create(static::TESTED_TYPE, null, [
            'choices' => $this->groupedChoices,
            'preferred_choices' => ['b', 'd'],
        ])
            ->createView();

        $this->assertEquals([
            'Symfony' => new ChoiceGroupView('Symfony', [
                0 => new ChoiceView('a', 'a', 'Bernhard'),
                2 => new ChoiceView('c', 'c', 'Kris'),
            ]),
            'Doctrine' => new ChoiceGroupView('Doctrine', [
                4 => new ChoiceView('e', 'e', 'Roman'),
            ]),
        ], $view->vars['choices']);
        $this->assertEquals([
            'Symfony' => new ChoiceGroupView('Symfony', [
                1 => new ChoiceView('b', 'b', 'Fabien'),
            ]),
            'Doctrine' => new ChoiceGroupView('Doctrine', [
                3 => new ChoiceView('d', 'd', 'Jon'),
            ]),
        ], $view->vars['preferred_choices']);
    }

    public function testPassChoiceDataToView()
    {
        $obj1 = (object) ['value' => 'a', 'label' => 'A'];
        $obj2 = (object) ['value' => 'b', 'label' => 'B'];
        $obj3 = (object) ['value' => 'c', 'label' => 'C'];
        $obj4 = (object) ['value' => 'd', 'label' => 'D'];
        $view = $this->factory->create(static::TESTED_TYPE, null, [
            'choices' => [$obj1, $obj2, $obj3, $obj4],
            'choice_label' => 'label',
            'choice_value' => 'value',
        ])
            ->createView();

        $this->assertEquals([
            new ChoiceView($obj1, 'a', 'A'),
            new ChoiceView($obj2, 'b', 'B'),
            new ChoiceView($obj3, 'c', 'C'),
            new ChoiceView($obj4, 'd', 'D'),
        ], $view->vars['choices']);
    }

    public function testAdjustFullNameForMultipleNonExpanded()
    {
        $view = $this->factory->createNamed('name', static::TESTED_TYPE, null, [
            'multiple' => true,
            'expanded' => false,
            'choices' => $this->choices,
        ])
            ->createView();

        $this->assertSame('name[]', $view->vars['full_name']);
    }

    // https://github.com/symfony/symfony/issues/3298
    public function testInitializeWithEmptyChoices()
    {
        $this->assertInstanceOf('Symfony\Component\Form\FormInterface', $this->factory->createNamed('name', static::TESTED_TYPE, null, [
            'choices' => [],
        ]));
    }

    public function testInitializeWithDefaultObjectChoice()
    {
        $obj1 = (object) ['value' => 'a', 'label' => 'A'];
        $obj2 = (object) ['value' => 'b', 'label' => 'B'];
        $obj3 = (object) ['value' => 'c', 'label' => 'C'];
        $obj4 = (object) ['value' => 'd', 'label' => 'D'];

        $form = $this->factory->create(static::TESTED_TYPE, null, [
            'choices' => [$obj1, $obj2, $obj3, $obj4],
            'choice_label' => 'label',
            'choice_value' => 'value',
            // Used to break because "data_class" was inferred, which needs to
            // remain null in every case (because it refers to the view format)
            'data' => $obj3,
        ]);

        // Trigger data initialization
        $this->assertSame('c', $form->getViewData());
    }

    /**
     * This covers the case when:
     *  - Custom choice type added after a choice type.
     *  - Custom type is expanded.
     *  - Custom type replaces 'choices' normalizer with a custom one.
     * In this case, custom type should not inherit labels from the first added choice type.
     */
    public function testCustomChoiceTypeDoesNotInheritChoiceLabels()
    {
        $builder = $this->factory->createBuilder();
        $builder->add('choice', static::TESTED_TYPE, [
                'choices' => [
                    '1' => '1',
                    '2' => '2',
                ],
            ]
        );
        $builder->add('subChoice', 'Symfony\Component\Form\Tests\Fixtures\ChoiceSubType');
        $form = $builder->getForm();

        // The default 'choices' normalizer would fill the $choiceLabels, but it has been replaced
        // in the custom choice type, so $choiceLabels->labels remains empty array.
        // In this case the 'choice_label' closure returns null and not the closure from the first choice type.
        $this->assertNull($form->get('subChoice')->getConfig()->getOption('choice_label'));
    }

    /**
     * @dataProvider invalidNestedValueTestMatrix
     */
    public function testSubmitInvalidNestedValue($multiple, $expanded, $submissionData)
    {
        $form = $this->factory->create(static::TESTED_TYPE, null, [
            'choices' => $this->choices,
            'multiple' => $multiple,
            'expanded' => $expanded,
        ]);

        $form->submit($submissionData);
        $this->assertFalse($form->isSynchronized());
        $this->assertEquals('All choices submitted must be NULL, strings or ints.', $form->getTransformationFailure()->getMessage());
    }

    public function invalidNestedValueTestMatrix()
    {
        return [
            'non-multiple, non-expanded' => [false, false, [[]]],
            'non-multiple, expanded' => [false, true, [[]]],
            'multiple, non-expanded' => [true, false, [[]]],
            'multiple, expanded' => [true, true, [[]]],
        ];
    }

    public function testInheritTranslationDomainFromParent()
    {
        $view = $this->factory
            ->createNamedBuilder('parent', FormTypeTest::TESTED_TYPE, null, [
                'translation_domain' => 'domain',
            ])
            ->add('child', static::TESTED_TYPE)
            ->getForm()
            ->createView();

        $this->assertEquals('domain', $view['child']->vars['translation_domain']);
    }

    public function testPassTranslationDomainToView()
    {
        $view = $this->factory->create(static::TESTED_TYPE, null, [
            'translation_domain' => 'domain',
        ])
            ->createView();

        $this->assertSame('domain', $view->vars['translation_domain']);
    }

    public function testPreferOwnTranslationDomain()
    {
        $view = $this->factory
            ->createNamedBuilder('parent', FormTypeTest::TESTED_TYPE, null, [
                'translation_domain' => 'parent_domain',
            ])
            ->add('child', static::TESTED_TYPE, [
                'translation_domain' => 'domain',
            ])
            ->getForm()
            ->createView();

        $this->assertEquals('domain', $view['child']->vars['translation_domain']);
    }

    public function testDefaultTranslationDomain()
    {
        $view = $this->factory->createNamedBuilder('parent', FormTypeTest::TESTED_TYPE)
            ->add('child', static::TESTED_TYPE)
            ->getForm()
            ->createView();

        $this->assertNull($view['child']->vars['translation_domain']);
    }

    public function testPassMultipartFalseToView()
    {
        $view = $this->factory->create(static::TESTED_TYPE, null)
            ->createView();

        $this->assertFalse($view->vars['multipart']);
    }

    public function testPassLabelToView()
    {
        $view = $this->factory->createNamed('__test___field', static::TESTED_TYPE, null, [
            'label' => 'My label',
        ])
            ->createView();

        $this->assertSame('My label', $view->vars['label']);
    }

    public function testPassIdAndNameToViewWithGrandParent()
    {
        $builder = $this->factory->createNamedBuilder('parent', FormTypeTest::TESTED_TYPE)
            ->add('child', FormTypeTest::TESTED_TYPE);
        $builder->get('child')->add('grand_child', static::TESTED_TYPE);
        $view = $builder->getForm()->createView();

        $this->assertEquals('parent_child_grand_child', $view['child']['grand_child']->vars['id']);
        $this->assertEquals('grand_child', $view['child']['grand_child']->vars['name']);
        $this->assertEquals('parent[child][grand_child]', $view['child']['grand_child']->vars['full_name']);
    }

    public function testPassIdAndNameToViewWithParent()
    {
        $view = $this->factory->createNamedBuilder('parent', FormTypeTest::TESTED_TYPE)
            ->add('child', static::TESTED_TYPE)
            ->getForm()
            ->createView();

        $this->assertEquals('parent_child', $view['child']->vars['id']);
        $this->assertEquals('child', $view['child']->vars['name']);
        $this->assertEquals('parent[child]', $view['child']->vars['full_name']);
    }

    public function testPassDisabledAsOption()
    {
        $form = $this->factory->create(static::TESTED_TYPE, null, [
            'disabled' => true,
        ]);

        $this->assertTrue($form->isDisabled());
    }

    public function testPassIdAndNameToView()
    {
        $view = $this->factory->createNamed('name', static::TESTED_TYPE, null)
            ->createView();

        $this->assertEquals('name', $view->vars['id']);
        $this->assertEquals('name', $view->vars['name']);
        $this->assertEquals('name', $view->vars['full_name']);
    }

    public function testStripLeadingUnderscoresAndDigitsFromId()
    {
        $view = $this->factory->createNamed('_09name', static::TESTED_TYPE, null)
            ->createView();

        $this->assertEquals('name', $view->vars['id']);
        $this->assertEquals('_09name', $view->vars['name']);
        $this->assertEquals('_09name', $view->vars['full_name']);
    }

    /**
     * @dataProvider provideTrimCases
     */
    public function testTrimIsDisabled($multiple, $expanded)
    {
        $form = $this->factory->create(static::TESTED_TYPE, null, [
            'multiple' => $multiple,
            'expanded' => $expanded,
            'choices' => [
                'a' => '1',
            ],
        ]);

        $submittedData = ' 1';

        $form->submit($multiple ? (array) $submittedData : $submittedData);

        // When the choice does not exist the transformation fails
        $this->assertFalse($form->isSynchronized());
        $this->assertNull($form->getData());
    }

    /**
     * @dataProvider provideTrimCases
     */
    public function testSubmitValueWithWhiteSpace($multiple, $expanded)
    {
        $valueWhitWhiteSpace = '1 ';

        $form = $this->factory->create(static::TESTED_TYPE, null, [
            'multiple' => $multiple,
            'expanded' => $expanded,
            'choices' => [
                'a' => $valueWhitWhiteSpace,
            ],
        ]);

        $form->submit($multiple ? (array) $valueWhitWhiteSpace : $valueWhitWhiteSpace);

        $this->assertTrue($form->isSynchronized());
        $this->assertSame($multiple ? (array) $valueWhitWhiteSpace : $valueWhitWhiteSpace, $form->getData());
    }

    public function provideTrimCases()
    {
        return [
            'Simple' => [false, false],
            'Multiple' => [true, false],
            'Simple expanded' => [false, true],
            'Multiple expanded' => [true, true],
        ];
    }
}
