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
use Symfony\Component\Form\Extension\Core\ChoiceList\ObjectChoiceList;

class ChoiceTypeTest extends \Symfony\Component\Form\Test\TypeTestCase
{
    private $choices = array(
        'Bernhard' => 'a',
        'Fabien' => 'b',
        'Kris' => 'c',
        'Jon' => 'd',
        'Roman' => 'e',
    );

    private $scalarChoices = array(
        'Yes' => true,
        'No' => false,
        'n/a' => '',
    );

    private $booleanChoicesWithNull = array(
        'Yes' => true,
        'No' => false,
        'n/a' => null,
    );

    private $numericChoicesFlipped = array(
        0 => 'Bernhard',
        1 => 'Fabien',
        2 => 'Kris',
        3 => 'Jon',
        4 => 'Roman',
    );

    private $objectChoices;

    protected $groupedChoices = array(
        'Symfony' => array(
            'Bernhard' => 'a',
            'Fabien' => 'b',
            'Kris' => 'c',
        ),
        'Doctrine' => array(
            'Jon' => 'd',
            'Roman' => 'e',
        ),
    );

    protected $groupedChoicesFlipped = array(
        'Symfony' => array(
            'a' => 'Bernhard',
            'b' => 'Fabien',
            'c' => 'Kris',
        ),
        'Doctrine' => array(
            'd' => 'Jon',
            'e' => 'Roman',
        ),
    );

    protected function setUp()
    {
        parent::setUp();

        $this->objectChoices = array(
            (object) array('id' => 1, 'name' => 'Bernhard'),
            (object) array('id' => 2, 'name' => 'Fabien'),
            (object) array('id' => 3, 'name' => 'Kris'),
            (object) array('id' => 4, 'name' => 'Jon'),
            (object) array('id' => 5, 'name' => 'Roman'),
        );
    }

    protected function tearDown()
    {
        parent::tearDown();

        $this->objectChoices = null;
    }

    /**
     * @group legacy
     */
    public function testLegacyName()
    {
        $form = $this->factory->create('choice');

        $this->assertSame('choice', $form->getConfig()->getType()->getName());
    }

    /**
     * @expectedException \Symfony\Component\OptionsResolver\Exception\InvalidOptionsException
     */
    public function testChoicesOptionExpectsArrayOrTraversable()
    {
        $this->factory->create('Symfony\Component\Form\Extension\Core\Type\ChoiceType', null, array(
            'choices' => new \stdClass(),
        ));
    }

    /**
     * @expectedException \Symfony\Component\OptionsResolver\Exception\InvalidOptionsException
     */
    public function testChoiceListOptionExpectsChoiceListInterface()
    {
        $this->factory->create('Symfony\Component\Form\Extension\Core\Type\ChoiceType', null, array(
            'choice_list' => array('foo' => 'foo'),
        ));
    }

    /**
     * @expectedException \Symfony\Component\OptionsResolver\Exception\InvalidOptionsException
     */
    public function testChoiceLoaderOptionExpectsChoiceLoaderInterface()
    {
        $this->factory->create('Symfony\Component\Form\Extension\Core\Type\ChoiceType', null, array(
            'choice_loader' => new \stdClass(),
        ));
    }

    public function testChoiceListAndChoicesCanBeEmpty()
    {
        $this->factory->create('Symfony\Component\Form\Extension\Core\Type\ChoiceType', null, array(
            'choices_as_values' => true,
        ));
    }

    public function testExpandedChoicesOptionsTurnIntoChildren()
    {
        $form = $this->factory->create('Symfony\Component\Form\Extension\Core\Type\ChoiceType', null, array(
            'expanded' => true,
            'choices' => $this->choices,
            'choices_as_values' => true,
        ));

        $this->assertCount(count($this->choices), $form, 'Each choice should become a new field');
    }

    /**
     * @group legacy
     */
    public function testExpandedFlippedChoicesOptionsTurnIntoChildren()
    {
        $form = $this->factory->create('Symfony\Component\Form\Extension\Core\Type\ChoiceType', null, array(
            'expanded' => true,
            'choices' => array_flip($this->choices),
        ));

        $this->assertCount(count($this->choices), $form, 'Each choice should become a new field');
    }

    public function testChoiceListWithScalarValues()
    {
        $view = $this->factory->create('Symfony\Component\Form\Extension\Core\Type\ChoiceType', null, array(
            'choices' => $this->scalarChoices,
            'choices_as_values' => true,
        ))->createView();

        $this->assertSame('1', $view->vars['choices'][0]->value);
        $this->assertSame('0', $view->vars['choices'][1]->value);
        $this->assertSame('', $view->vars['choices'][2]->value);
        $this->assertFalse($view->vars['is_selected']($view->vars['choices'][0], $view->vars['value']), 'True value should not be pre selected');
        $this->assertFalse($view->vars['is_selected']($view->vars['choices'][1], $view->vars['value']), 'False value should not be pre selected');
        $this->assertFalse($view->vars['is_selected']($view->vars['choices'][2], $view->vars['value']), 'Empty value should not be pre selected');
    }

    public function testChoiceListWithScalarValuesAndFalseAsPreSetData()
    {
        $view = $this->factory->create('Symfony\Component\Form\Extension\Core\Type\ChoiceType', false, array(
            'choices' => $this->scalarChoices,
            'choices_as_values' => true,
        ))->createView();

        $this->assertTrue($view->vars['is_selected']($view->vars['choices'][1]->value, $view->vars['value']), 'False value should be pre selected');
    }

    public function testExpandedChoiceListWithScalarValues()
    {
        $view = $this->factory->create('Symfony\Component\Form\Extension\Core\Type\ChoiceType', null, array(
            'choices' => $this->scalarChoices,
            'choices_as_values' => true,
            'expanded' => true,
        ))->createView();

        $this->assertFalse($view->children[0]->vars['checked'], 'True value should not be pre selected');
        $this->assertFalse($view->children[1]->vars['checked'], 'False value should not be pre selected');
        $this->assertTrue($view->children[2]->vars['checked'], 'Empty value should be pre selected');
    }

    public function testExpandedChoiceListWithBooleanAndNullValues()
    {
        $view = $this->factory->create('Symfony\Component\Form\Extension\Core\Type\ChoiceType', null, array(
            'choices' => $this->booleanChoicesWithNull,
            'choices_as_values' => true,
            'expanded' => true,
        ))->createView();

        $this->assertFalse($view->children[0]->vars['checked'], 'True value should not be pre selected');
        $this->assertFalse($view->children[1]->vars['checked'], 'False value should not be pre selected');
        $this->assertTrue($view->children[2]->vars['checked'], 'Empty value should be pre selected');
    }

    public function testExpandedChoiceListWithScalarValuesAndFalseAsPreSetData()
    {
        $view = $this->factory->create('Symfony\Component\Form\Extension\Core\Type\ChoiceType', false, array(
            'choices' => $this->scalarChoices,
            'choices_as_values' => true,
            'expanded' => true,
        ))->createView();

        $this->assertSame('1', $view->vars['choices'][0]->value);
        $this->assertSame('0', $view->vars['choices'][1]->value);
        $this->assertTrue($view->children[1]->vars['checked'], 'False value should be pre selected');
        $this->assertFalse($view->children[2]->vars['checked'], 'Empty value should not be pre selected');
    }

    public function testExpandedChoiceListWithBooleanAndNullValuesAndFalseAsPreSetData()
    {
        $view = $this->factory->create('Symfony\Component\Form\Extension\Core\Type\ChoiceType', false, array(
            'choices' => $this->booleanChoicesWithNull,
            'choices_as_values' => true,
            'expanded' => true,
        ))->createView();

        $this->assertFalse($view->children[0]->vars['checked'], 'True value should not be pre selected');
        $this->assertTrue($view->children[1]->vars['checked'], 'False value should be pre selected');
        $this->assertFalse($view->children[2]->vars['checked'], 'Null value should not be pre selected');
    }

    public function testPlaceholderPresentOnNonRequiredExpandedSingleChoice()
    {
        $form = $this->factory->create('Symfony\Component\Form\Extension\Core\Type\ChoiceType', null, array(
            'multiple' => false,
            'expanded' => true,
            'required' => false,
            'choices' => $this->choices,
            'choices_as_values' => true,
        ));

        $this->assertTrue(isset($form['placeholder']));
        $this->assertCount(count($this->choices) + 1, $form, 'Each choice should become a new field');
    }

    public function testPlaceholderNotPresentIfRequired()
    {
        $form = $this->factory->create('Symfony\Component\Form\Extension\Core\Type\ChoiceType', null, array(
            'multiple' => false,
            'expanded' => true,
            'required' => true,
            'choices' => $this->choices,
            'choices_as_values' => true,
        ));

        $this->assertFalse(isset($form['placeholder']));
        $this->assertCount(count($this->choices), $form, 'Each choice should become a new field');
    }

    public function testPlaceholderNotPresentIfMultiple()
    {
        $form = $this->factory->create('Symfony\Component\Form\Extension\Core\Type\ChoiceType', null, array(
            'multiple' => true,
            'expanded' => true,
            'required' => false,
            'choices' => $this->choices,
            'choices_as_values' => true,
        ));

        $this->assertFalse(isset($form['placeholder']));
        $this->assertCount(count($this->choices), $form, 'Each choice should become a new field');
    }

    public function testPlaceholderNotPresentIfEmptyChoice()
    {
        $form = $this->factory->create('Symfony\Component\Form\Extension\Core\Type\ChoiceType', null, array(
            'multiple' => false,
            'expanded' => true,
            'required' => false,
            'choices' => array(
                'Empty' => '',
                'Not empty' => 1,
            ),
            'choices_as_values' => true,
        ));

        $this->assertFalse(isset($form['placeholder']));
        $this->assertCount(2, $form, 'Each choice should become a new field');
    }

    public function testPlaceholderWithBooleanChoices()
    {
        $form = $this->factory->create('Symfony\Component\Form\Extension\Core\Type\ChoiceType', null, array(
            'multiple' => false,
            'expanded' => false,
            'required' => false,
            'choices' => array(
                'Yes' => true,
                'No' => false,
            ),
            'placeholder' => 'Select an option',
            'choices_as_values' => true,
        ));

        $view = $form->createView();

        $this->assertSame('', $view->vars['value'], 'Value should be empty');
        $this->assertSame('1', $view->vars['choices'][0]->value);
        $this->assertSame('0', $view->vars['choices'][1]->value, 'Choice "false" should have "0" as value');
        $this->assertFalse($view->vars['is_selected']($view->vars['choices'][1]->value, $view->vars['value']), 'Choice "false" should not be selected');
    }

    public function testPlaceholderWithBooleanChoicesWithFalseAsPreSetData()
    {
        $form = $this->factory->create('Symfony\Component\Form\Extension\Core\Type\ChoiceType', false, array(
            'multiple' => false,
            'expanded' => false,
            'required' => false,
            'choices' => array(
                'Yes' => true,
                'No' => false,
            ),
            'placeholder' => 'Select an option',
            'choices_as_values' => true,
        ));

        $view = $form->createView();

        $this->assertSame('0', $view->vars['value'], 'Value should be "0"');
        $this->assertSame('1', $view->vars['choices'][0]->value);
        $this->assertSame('0', $view->vars['choices'][1]->value, 'Choice "false" should have "0" as value');
        $this->assertTrue($view->vars['is_selected']($view->vars['choices'][1]->value, $view->vars['value']), 'Choice "false" should be selected');
    }

    public function testPlaceholderWithExpandedBooleanChoices()
    {
        $form = $this->factory->create('Symfony\Component\Form\Extension\Core\Type\ChoiceType', null, array(
            'multiple' => false,
            'expanded' => true,
            'required' => false,
            'choices' => array(
                'Yes' => true,
                'No' => false,
            ),
            'placeholder' => 'Select an option',
            'choices_as_values' => true,
        ));

        $this->assertTrue(isset($form['placeholder']), 'Placeholder should be set');
        $this->assertCount(3, $form, 'Each choice should become a new field, placeholder included');

        $view = $form->createView();

        $this->assertSame('', $view->vars['value'], 'Value should be an empty string');
        $this->assertSame('1', $view->vars['choices'][0]->value);
        $this->assertSame('0', $view->vars['choices'][1]->value, 'Choice "false" should have "0" as value');
        $this->assertFalse($view->children[1]->vars['checked'], 'Choice "false" should not be selected');
    }

    public function testPlaceholderWithExpandedBooleanChoicesAndWithFalseAsPreSetData()
    {
        $form = $this->factory->create('Symfony\Component\Form\Extension\Core\Type\ChoiceType', false, array(
            'multiple' => false,
            'expanded' => true,
            'required' => false,
            'choices' => array(
                'Yes' => true,
                'No' => false,
            ),
            'placeholder' => 'Select an option',
            'choices_as_values' => true,
        ));

        $this->assertTrue(isset($form['placeholder']), 'Placeholder should be set');
        $this->assertCount(3, $form, 'Each choice should become a new field, placeholder included');

        $view = $form->createView();

        $this->assertSame('0', $view->vars['value'], 'Value should be "0"');
        $this->assertSame('1', $view->vars['choices'][0]->value);
        $this->assertSame('0', $view->vars['choices'][1]->value, 'Choice "false" should have "0" as value');
        $this->assertTrue($view->children[1]->vars['checked'], 'Choice "false" should be selected');
    }

    public function testExpandedChoicesOptionsAreFlattened()
    {
        $form = $this->factory->create('Symfony\Component\Form\Extension\Core\Type\ChoiceType', null, array(
            'expanded' => true,
            'choices' => $this->groupedChoices,
            'choices_as_values' => true,
        ));

        $flattened = array();
        foreach ($this->groupedChoices as $choices) {
            $flattened = array_merge($flattened, array_keys($choices));
        }

        $this->assertCount($form->count(), $flattened, 'Each nested choice should become a new field, not the groups');

        foreach ($flattened as $value => $choice) {
            $this->assertTrue($form->has($value), 'Flattened choice is named after it\'s value');
        }
    }

    /**
     * @group legacy
     */
    public function testExpandedChoicesFlippedOptionsAreFlattened()
    {
        $form = $this->factory->create('choice', null, array(
            'expanded' => true,
            'choices' => $this->groupedChoicesFlipped,
        ));

        $flattened = array();
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
        $obj1 = (object) array('id' => 1, 'name' => 'Bernhard');
        $obj2 = (object) array('id' => 2, 'name' => 'Fabien');
        $obj3 = (object) array('id' => 3, 'name' => 'Kris');
        $obj4 = (object) array('id' => 4, 'name' => 'Jon');
        $obj5 = (object) array('id' => 5, 'name' => 'Roman');

        $form = $this->factory->create('Symfony\Component\Form\Extension\Core\Type\ChoiceType', null, array(
            'expanded' => true,
            'choices' => array(
                'Symfony' => array($obj1, $obj2, $obj3),
                'Doctrine' => array($obj4, $obj5),
            ),
            'choices_as_values' => true,
            'choice_name' => 'id',
        ));

        $this->assertSame(5, $form->count(), 'Each nested choice should become a new field, not the groups');
        $this->assertTrue($form->has(1));
        $this->assertTrue($form->has(2));
        $this->assertTrue($form->has(3));
        $this->assertTrue($form->has(4));
        $this->assertTrue($form->has(5));
    }

    public function testExpandedCheckboxesAreNeverRequired()
    {
        $form = $this->factory->create('Symfony\Component\Form\Extension\Core\Type\ChoiceType', null, array(
            'multiple' => true,
            'expanded' => true,
            'required' => true,
            'choices' => $this->choices,
            'choices_as_values' => true,
        ));

        foreach ($form as $child) {
            $this->assertFalse($child->isRequired());
        }
    }

    public function testExpandedRadiosAreRequiredIfChoiceChildIsRequired()
    {
        $form = $this->factory->create('Symfony\Component\Form\Extension\Core\Type\ChoiceType', null, array(
            'multiple' => false,
            'expanded' => true,
            'required' => true,
            'choices' => $this->choices,
            'choices_as_values' => true,
        ));

        foreach ($form as $child) {
            $this->assertTrue($child->isRequired());
        }
    }

    public function testExpandedRadiosAreNotRequiredIfChoiceChildIsNotRequired()
    {
        $form = $this->factory->create('Symfony\Component\Form\Extension\Core\Type\ChoiceType', null, array(
            'multiple' => false,
            'expanded' => true,
            'required' => false,
            'choices' => $this->choices,
            'choices_as_values' => true,
        ));

        foreach ($form as $child) {
            $this->assertFalse($child->isRequired());
        }
    }

    public function testSubmitSingleNonExpanded()
    {
        $form = $this->factory->create('Symfony\Component\Form\Extension\Core\Type\ChoiceType', null, array(
            'multiple' => false,
            'expanded' => false,
            'choices' => $this->choices,
            'choices_as_values' => true,
        ));

        $form->submit('b');

        $this->assertEquals('b', $form->getData());
        $this->assertEquals('b', $form->getViewData());
        $this->assertTrue($form->isSynchronized());
    }

    public function testSubmitSingleNonExpandedInvalidChoice()
    {
        $form = $this->factory->create('Symfony\Component\Form\Extension\Core\Type\ChoiceType', null, array(
            'multiple' => false,
            'expanded' => false,
            'choices' => $this->choices,
            'choices_as_values' => true,
        ));

        $form->submit('foobar');

        $this->assertNull($form->getData());
        $this->assertEquals('foobar', $form->getViewData());
        $this->assertFalse($form->isSynchronized());
    }

    public function testSubmitSingleNonExpandedNull()
    {
        $form = $this->factory->create('Symfony\Component\Form\Extension\Core\Type\ChoiceType', null, array(
            'multiple' => false,
            'expanded' => false,
            'choices' => $this->choices,
            'choices_as_values' => true,
        ));

        $form->submit(null);

        $this->assertNull($form->getData());
        $this->assertSame('', $form->getViewData());
        $this->assertTrue($form->isSynchronized());
    }

    // In edge cases (for example, when choices are loaded dynamically by a
    // loader), the choices may be empty. Make sure to behave the same as when
    // choices are available.
    public function testSubmitSingleNonExpandedNullNoChoices()
    {
        $form = $this->factory->create('Symfony\Component\Form\Extension\Core\Type\ChoiceType', null, array(
            'multiple' => false,
            'expanded' => false,
            'choices' => array(),
            'choices_as_values' => true,
        ));

        $form->submit(null);

        $this->assertNull($form->getData());
        $this->assertSame('', $form->getViewData());
        $this->assertTrue($form->isSynchronized());
    }

    public function testSubmitSingleNonExpandedEmpty()
    {
        $form = $this->factory->create('Symfony\Component\Form\Extension\Core\Type\ChoiceType', null, array(
            'multiple' => false,
            'expanded' => false,
            'choices' => $this->choices,
            'choices_as_values' => true,
        ));

        $form->submit('');

        $this->assertNull($form->getData());
        $this->assertSame('', $form->getViewData());
        $this->assertTrue($form->isSynchronized());
    }

    public function testSubmitSingleNonExpandedEmptyExplicitEmptyChoice()
    {
        $form = $this->factory->create('Symfony\Component\Form\Extension\Core\Type\ChoiceType', null, array(
            'multiple' => false,
            'expanded' => false,
            'choices' => array(
                'Empty' => 'EMPTY_CHOICE',
            ),
            'choices_as_values' => true,
            'choice_value' => function () {
                return '';
            },
        ));

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
        $form = $this->factory->create('Symfony\Component\Form\Extension\Core\Type\ChoiceType', null, array(
            'multiple' => false,
            'expanded' => false,
            'choices' => array(),
            'choices_as_values' => true,
        ));

        $form->submit('');

        $this->assertNull($form->getData());
        $this->assertSame('', $form->getViewData());
        $this->assertTrue($form->isSynchronized());
    }

    public function testSubmitSingleNonExpandedFalse()
    {
        $form = $this->factory->create('Symfony\Component\Form\Extension\Core\Type\ChoiceType', null, array(
            'multiple' => false,
            'expanded' => false,
            'choices' => $this->choices,
            'choices_as_values' => true,
        ));

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
        $form = $this->factory->create('Symfony\Component\Form\Extension\Core\Type\ChoiceType', null, array(
            'multiple' => false,
            'expanded' => false,
            'choices' => array(),
            'choices_as_values' => true,
        ));

        $form->submit(false);

        $this->assertNull($form->getData());
        $this->assertSame('', $form->getViewData());
        $this->assertTrue($form->isSynchronized());
    }

    public function testSubmitSingleNonExpandedObjectChoices()
    {
        $form = $this->factory->create('Symfony\Component\Form\Extension\Core\Type\ChoiceType', null, array(
            'multiple' => false,
            'expanded' => false,
            'choices' => $this->objectChoices,
            'choices_as_values' => true,
            'choice_label' => 'name',
            'choice_value' => 'id',
        ));

        // "id" value of the second entry
        $form->submit('2');

        $this->assertEquals($this->objectChoices[1], $form->getData());
        $this->assertEquals('2', $form->getViewData());
        $this->assertTrue($form->isSynchronized());
    }

    public function testSubmitSingleChoiceWithEmptyData()
    {
        $form = $this->factory->create('Symfony\Component\Form\Extension\Core\Type\ChoiceType', null, array(
            'multiple' => false,
            'expanded' => false,
            'choices' => array('test'),
            'choices_as_values' => true,
            'empty_data' => 'test',
        ));

        $form->submit(null);

        $this->assertSame('test', $form->getData());
    }

    public function testSubmitMultipleChoiceWithEmptyData()
    {
        $form = $this->factory->create('Symfony\Component\Form\Extension\Core\Type\ChoiceType', null, array(
            'multiple' => true,
            'expanded' => false,
            'choices' => array('test'),
            'choices_as_values' => true,
            'empty_data' => array('test'),
        ));

        $form->submit(null);

        $this->assertSame(array('test'), $form->getData());
    }

    public function testSubmitSingleChoiceExpandedWithEmptyData()
    {
        $form = $this->factory->create('Symfony\Component\Form\Extension\Core\Type\ChoiceType', null, array(
            'multiple' => false,
            'expanded' => true,
            'choices' => array('test'),
            'choices_as_values' => true,
            'empty_data' => 'test',
        ));

        $form->submit(null);

        $this->assertSame('test', $form->getData());
    }

    public function testSubmitMultipleChoiceExpandedWithEmptyData()
    {
        $form = $this->factory->create('Symfony\Component\Form\Extension\Core\Type\ChoiceType', null, array(
            'multiple' => true,
            'expanded' => true,
            'choices' => array('test'),
            'choices_as_values' => true,
            'empty_data' => array('test'),
        ));

        $form->submit(null);

        $this->assertSame(array('test'), $form->getData());
    }

    /**
     * @group legacy
     */
    public function testLegacyNullChoices()
    {
        $form = $this->factory->create('choice', null, array(
            'multiple' => false,
            'expanded' => false,
            'choices' => null,
        ));
        $this->assertNull($form->getConfig()->getOption('choices'));
        $this->assertFalse($form->getConfig()->getOption('multiple'));
        $this->assertFalse($form->getConfig()->getOption('expanded'));
    }

    /**
     * @group legacy
     */
    public function testLegacySubmitSingleNonExpandedObjectChoices()
    {
        $form = $this->factory->create('Symfony\Component\Form\Extension\Core\Type\ChoiceType', null, array(
            'multiple' => false,
            'expanded' => false,
            'choice_list' => new ObjectChoiceList(
                $this->objectChoices,
                // label path
                'name',
                array(),
                null,
                // value path
                'id'
            ),
        ));

        // "id" value of the second entry
        $form->submit('2');

        $this->assertEquals($this->objectChoices[1], $form->getData());
        $this->assertEquals('2', $form->getViewData());
        $this->assertTrue($form->isSynchronized());
    }

    public function testSubmitMultipleNonExpanded()
    {
        $form = $this->factory->create('Symfony\Component\Form\Extension\Core\Type\ChoiceType', null, array(
            'multiple' => true,
            'expanded' => false,
            'choices' => $this->choices,
            'choices_as_values' => true,
        ));

        $form->submit(array('a', 'b'));

        $this->assertEquals(array('a', 'b'), $form->getData());
        $this->assertEquals(array('a', 'b'), $form->getViewData());
        $this->assertTrue($form->isSynchronized());
    }

    public function testSubmitMultipleNonExpandedEmpty()
    {
        $form = $this->factory->create('Symfony\Component\Form\Extension\Core\Type\ChoiceType', null, array(
            'multiple' => true,
            'expanded' => false,
            'choices' => $this->choices,
            'choices_as_values' => true,
        ));

        $form->submit(array());

        $this->assertSame(array(), $form->getData());
        $this->assertSame(array(), $form->getViewData());
        $this->assertTrue($form->isSynchronized());
    }

    // In edge cases (for example, when choices are loaded dynamically by a
    // loader), the choices may be empty. Make sure to behave the same as when
    // choices are available.
    public function testSubmitMultipleNonExpandedEmptyNoChoices()
    {
        $form = $this->factory->create('Symfony\Component\Form\Extension\Core\Type\ChoiceType', null, array(
            'multiple' => true,
            'expanded' => false,
            'choices' => array(),
            'choices_as_values' => true,
        ));

        $form->submit(array());

        $this->assertSame(array(), $form->getData());
        $this->assertSame(array(), $form->getViewData());
        $this->assertTrue($form->isSynchronized());
    }

    public function testSubmitMultipleNonExpandedInvalidScalarChoice()
    {
        $form = $this->factory->create('Symfony\Component\Form\Extension\Core\Type\ChoiceType', null, array(
            'multiple' => true,
            'expanded' => false,
            'choices' => $this->choices,
            'choices_as_values' => true,
        ));

        $form->submit('foobar');

        $this->assertNull($form->getData());
        $this->assertEquals('foobar', $form->getViewData());
        $this->assertFalse($form->isSynchronized());
    }

    public function testSubmitMultipleNonExpandedInvalidArrayChoice()
    {
        $form = $this->factory->create('Symfony\Component\Form\Extension\Core\Type\ChoiceType', null, array(
            'multiple' => true,
            'expanded' => false,
            'choices' => $this->choices,
            'choices_as_values' => true,
        ));

        $form->submit(array('a', 'foobar'));

        $this->assertNull($form->getData());
        $this->assertEquals(array('a', 'foobar'), $form->getViewData());
        $this->assertFalse($form->isSynchronized());
    }

    public function testSubmitMultipleNonExpandedObjectChoices()
    {
        $form = $this->factory->create('Symfony\Component\Form\Extension\Core\Type\ChoiceType', null, array(
            'multiple' => true,
            'expanded' => false,
            'choices' => $this->objectChoices,
            'choices_as_values' => true,
            'choice_label' => 'name',
            'choice_value' => 'id',
        ));

        $form->submit(array('2', '3'));

        $this->assertEquals(array($this->objectChoices[1], $this->objectChoices[2]), $form->getData());
        $this->assertEquals(array('2', '3'), $form->getViewData());
        $this->assertTrue($form->isSynchronized());
    }

    /**
     * @group legacy
     */
    public function testLegacySubmitMultipleNonExpandedObjectChoices()
    {
        $form = $this->factory->create('Symfony\Component\Form\Extension\Core\Type\ChoiceType', null, array(
            'multiple' => true,
            'expanded' => false,
            'choice_list' => new ObjectChoiceList(
                $this->objectChoices,
                // label path
                'name',
                array(),
                null,
                // value path
                'id'
            ),
        ));

        $form->submit(array('2', '3'));

        $this->assertEquals(array($this->objectChoices[1], $this->objectChoices[2]), $form->getData());
        $this->assertEquals(array('2', '3'), $form->getViewData());
        $this->assertTrue($form->isSynchronized());
    }

    public function testSubmitSingleExpandedRequired()
    {
        $form = $this->factory->create('Symfony\Component\Form\Extension\Core\Type\ChoiceType', null, array(
            'multiple' => false,
            'expanded' => true,
            'required' => true,
            'choices' => $this->choices,
            'choices_as_values' => true,
        ));

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
        $form = $this->factory->create('Symfony\Component\Form\Extension\Core\Type\ChoiceType', null, array(
            'multiple' => false,
            'expanded' => true,
            'required' => true,
            'choices' => $this->choices,
            'choices_as_values' => true,
        ));

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
        $form = $this->factory->create('Symfony\Component\Form\Extension\Core\Type\ChoiceType', null, array(
            'multiple' => false,
            'expanded' => true,
            'required' => false,
            'choices' => $this->choices,
            'choices_as_values' => true,
        ));

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
        $form = $this->factory->create('Symfony\Component\Form\Extension\Core\Type\ChoiceType', null, array(
            'multiple' => false,
            'expanded' => true,
            'required' => false,
            'choices' => $this->choices,
            'choices_as_values' => true,
        ));

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
        $form = $this->factory->create('Symfony\Component\Form\Extension\Core\Type\ChoiceType', null, array(
            'multiple' => false,
            'expanded' => true,
            'required' => true,
            'choices' => $this->choices,
            'choices_as_values' => true,
        ));

        $form->submit(null);

        $this->assertNull($form->getData());
        $this->assertSame('', $form->getViewData(), 'View data should always be a string');
        $this->assertSame(array(), $form->getExtraData(), 'ChoiceType is compound when expanded, extra data should always be an array');
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
        $form = $this->factory->create('Symfony\Component\Form\Extension\Core\Type\ChoiceType', null, array(
            'multiple' => false,
            'expanded' => true,
            'required' => true,
            'choices' => array(),
            'choices_as_values' => true,
        ));

        $form->submit(null);

        $this->assertNull($form->getData());
        $this->assertSame('', $form->getViewData(), 'View data should always be a string');
        $this->assertSame(array(), $form->getExtraData(), 'ChoiceType is compound when expanded, extra data should always be an array');
        $this->assertTrue($form->isSynchronized());
    }

    public function testSubmitSingleExpandedRequiredEmpty()
    {
        $form = $this->factory->create('Symfony\Component\Form\Extension\Core\Type\ChoiceType', null, array(
            'multiple' => false,
            'expanded' => true,
            'required' => true,
            'choices' => $this->choices,
            'choices_as_values' => true,
        ));

        $form->submit('');

        $this->assertNull($form->getData());
        $this->assertSame('', $form->getViewData(), 'View data should always be a string');
        $this->assertSame(array(), $form->getExtraData(), 'ChoiceType is compound when expanded, extra data should always be an array');
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
        $form = $this->factory->create('Symfony\Component\Form\Extension\Core\Type\ChoiceType', null, array(
            'multiple' => false,
            'expanded' => true,
            'required' => true,
            'choices' => array(),
            'choices_as_values' => true,
        ));

        $form->submit('');

        $this->assertNull($form->getData());
        $this->assertSame('', $form->getViewData(), 'View data should always be a string');
        $this->assertSame(array(), $form->getExtraData(), 'ChoiceType is compound when expanded, extra data should always be an array');
        $this->assertTrue($form->isSynchronized());
    }

    public function testSubmitSingleExpandedRequiredFalse()
    {
        $form = $this->factory->create('Symfony\Component\Form\Extension\Core\Type\ChoiceType', null, array(
            'multiple' => false,
            'expanded' => true,
            'required' => true,
            'choices' => $this->choices,
            'choices_as_values' => true,
        ));

        $form->submit(false);

        $this->assertNull($form->getData());
        $this->assertSame('', $form->getViewData(), 'View data should always be a string');
        $this->assertSame(array(), $form->getExtraData(), 'ChoiceType is compound when expanded, extra data should always be an array');
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
        $form = $this->factory->create('Symfony\Component\Form\Extension\Core\Type\ChoiceType', null, array(
            'multiple' => false,
            'expanded' => true,
            'required' => true,
            'choices' => array(),
            'choices_as_values' => true,
        ));

        $form->submit(false);

        $this->assertNull($form->getData());
        $this->assertSame('', $form->getViewData(), 'View data should always be a string');
        $this->assertSame(array(), $form->getExtraData(), 'ChoiceType is compound when expanded, extra data should always be an array');
        $this->assertTrue($form->isSynchronized());
    }

    public function testSubmitSingleExpandedNonRequiredNull()
    {
        $form = $this->factory->create('Symfony\Component\Form\Extension\Core\Type\ChoiceType', null, array(
            'multiple' => false,
            'expanded' => true,
            'required' => false,
            'choices' => $this->choices,
            'choices_as_values' => true,
        ));

        $form->submit(null);

        $this->assertNull($form->getData());
        $this->assertSame('', $form->getViewData(), 'View data should always be a string');
        $this->assertSame(array(), $form->getExtraData(), 'ChoiceType is compound when expanded, extra data should always be an array');
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
        $form = $this->factory->create('Symfony\Component\Form\Extension\Core\Type\ChoiceType', null, array(
            'multiple' => false,
            'expanded' => true,
            'required' => false,
            'choices' => array(),
            'choices_as_values' => true,
        ));

        $form->submit(null);

        $this->assertNull($form->getData());
        $this->assertSame('', $form->getViewData(), 'View data should always be a string');
        $this->assertSame(array(), $form->getExtraData(), 'ChoiceType is compound when expanded, extra data should always be an array');
        $this->assertTrue($form->isSynchronized());
    }

    public function testSubmitSingleExpandedNonRequiredEmpty()
    {
        $form = $this->factory->create('Symfony\Component\Form\Extension\Core\Type\ChoiceType', null, array(
            'multiple' => false,
            'expanded' => true,
            'required' => false,
            'choices' => $this->choices,
            'choices_as_values' => true,
        ));

        $form->submit('');

        $this->assertNull($form->getData());
        $this->assertSame('', $form->getViewData(), 'View data should always be a string');
        $this->assertSame(array(), $form->getExtraData(), 'ChoiceType is compound when expanded, extra data should always be an array');
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
        $form = $this->factory->create('Symfony\Component\Form\Extension\Core\Type\ChoiceType', null, array(
            'multiple' => false,
            'expanded' => true,
            'required' => false,
            'choices' => array(),
            'choices_as_values' => true,
        ));

        $form->submit('');

        $this->assertNull($form->getData());
        $this->assertSame('', $form->getViewData(), 'View data should always be a string');
        $this->assertSame(array(), $form->getExtraData(), 'ChoiceType is compound when expanded, extra data should always be an array');
        $this->assertTrue($form->isSynchronized());
    }

    public function testSubmitSingleExpandedNonRequiredFalse()
    {
        $form = $this->factory->create('Symfony\Component\Form\Extension\Core\Type\ChoiceType', null, array(
            'multiple' => false,
            'expanded' => true,
            'required' => false,
            'choices' => $this->choices,
            'choices_as_values' => true,
        ));

        $form->submit(false);

        $this->assertNull($form->getData());
        $this->assertSame('', $form->getViewData(), 'View data should always be a string');
        $this->assertSame(array(), $form->getExtraData(), 'ChoiceType is compound when expanded, extra data should always be an array');
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
        $form = $this->factory->create('Symfony\Component\Form\Extension\Core\Type\ChoiceType', null, array(
            'multiple' => false,
            'expanded' => true,
            'required' => false,
            'choices' => array(),
            'choices_as_values' => true,
        ));

        $form->submit(false);

        $this->assertNull($form->getData());
        $this->assertSame('', $form->getViewData(), 'View data should always be a string');
        $this->assertSame(array(), $form->getExtraData(), 'ChoiceType is compound when expanded, extra data should always be an array');
        $this->assertTrue($form->isSynchronized());
    }

    public function testSubmitSingleExpandedWithEmptyChild()
    {
        $form = $this->factory->create('Symfony\Component\Form\Extension\Core\Type\ChoiceType', null, array(
            'multiple' => false,
            'expanded' => true,
            'choices' => array(
                'Empty' => '',
                'Not empty' => 1,
            ),
            'choices_as_values' => true,
        ));

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
        $form = $this->factory->create('Symfony\Component\Form\Extension\Core\Type\ChoiceType', null, array(
            'multiple' => false,
            'expanded' => true,
            'choices' => $this->objectChoices,
            'choices_as_values' => true,
            'choice_label' => 'name',
            'choice_value' => 'id',
        ));

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

    /**
     * @group legacy
     */
    public function testLegacySubmitSingleExpandedObjectChoices()
    {
        $form = $this->factory->create('Symfony\Component\Form\Extension\Core\Type\ChoiceType', null, array(
            'multiple' => false,
            'expanded' => true,
            'choice_list' => new ObjectChoiceList(
                $this->objectChoices,
                // label path
                'name',
                array(),
                null,
                // value path
                'id'
            ),
        ));

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

    /**
     * @group legacy
     */
    public function testSubmitSingleExpandedNumericChoicesFlipped()
    {
        $form = $this->factory->create('Symfony\Component\Form\Extension\Core\Type\ChoiceType', null, array(
            'multiple' => false,
            'expanded' => true,
            'choices' => $this->numericChoicesFlipped,
        ));

        $form->submit('1');

        $this->assertSame(1, $form->getData());
        $this->assertTrue($form->isSynchronized());

        $this->assertFalse($form[0]->getData());
        $this->assertTrue($form[1]->getData());
        $this->assertFalse($form[2]->getData());
        $this->assertFalse($form[3]->getData());
        $this->assertFalse($form[4]->getData());
        $this->assertNull($form[0]->getViewData());
        $this->assertSame('1', $form[1]->getViewData());
        $this->assertNull($form[2]->getViewData());
        $this->assertNull($form[3]->getViewData());
        $this->assertNull($form[4]->getViewData());
    }

    public function testSubmitMultipleExpanded()
    {
        $form = $this->factory->create('Symfony\Component\Form\Extension\Core\Type\ChoiceType', null, array(
            'multiple' => true,
            'expanded' => true,
            'choices' => $this->choices,
            'choices_as_values' => true,
        ));

        $form->submit(array('a', 'c'));

        $this->assertSame(array('a', 'c'), $form->getData());
        $this->assertSame(array('a', 'c'), $form->getViewData());
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
        $form = $this->factory->create('Symfony\Component\Form\Extension\Core\Type\ChoiceType', null, array(
            'multiple' => true,
            'expanded' => true,
            'choices' => $this->choices,
            'choices_as_values' => true,
        ));

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
        $form = $this->factory->create('Symfony\Component\Form\Extension\Core\Type\ChoiceType', null, array(
            'multiple' => true,
            'expanded' => true,
            'choices' => $this->choices,
            'choices_as_values' => true,
        ));

        $form->submit(array('a', 'foobar'));

        $this->assertNull($form->getData());
        $this->assertSame(array('a', 'foobar'), $form->getViewData());
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
        $form = $this->factory->create('Symfony\Component\Form\Extension\Core\Type\ChoiceType', null, array(
            'multiple' => true,
            'expanded' => true,
            'choices' => $this->choices,
            'choices_as_values' => true,
        ));

        $form->submit(array());

        $this->assertSame(array(), $form->getData());
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
        $form = $this->factory->create('Symfony\Component\Form\Extension\Core\Type\ChoiceType', null, array(
            'multiple' => true,
            'expanded' => true,
            'choices' => array(),
            'choices_as_values' => true,
        ));

        $form->submit(array());

        $this->assertSame(array(), $form->getData());
        $this->assertTrue($form->isSynchronized());
    }

    public function testSubmitMultipleExpandedWithEmptyChild()
    {
        $form = $this->factory->create('Symfony\Component\Form\Extension\Core\Type\ChoiceType', null, array(
            'multiple' => true,
            'expanded' => true,
            'choices' => array(
                'Empty' => '',
                'Not Empty' => 1,
                'Not Empty 2' => 2,
            ),
            'choices_as_values' => true,
        ));

        $form->submit(array('', '2'));

        $this->assertSame(array('', 2), $form->getData());
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
        $form = $this->factory->create('Symfony\Component\Form\Extension\Core\Type\ChoiceType', null, array(
            'multiple' => true,
            'expanded' => true,
            'choices' => $this->objectChoices,
            'choices_as_values' => true,
            'choice_label' => 'name',
            'choice_value' => 'id',
        ));

        $form->submit(array('1', '2'));

        $this->assertSame(array($this->objectChoices[0], $this->objectChoices[1]), $form->getData());
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

    /**
     * @group legacy
     */
    public function testLegacySubmitMultipleExpandedObjectChoices()
    {
        $form = $this->factory->create('Symfony\Component\Form\Extension\Core\Type\ChoiceType', null, array(
            'multiple' => true,
            'expanded' => true,
            'choice_list' => new ObjectChoiceList(
                $this->objectChoices,
                // label path
                'name',
                array(),
                null,
                // value path
                'id'
            ),
        ));

        $form->submit(array('1', '2'));

        $this->assertSame(array($this->objectChoices[0], $this->objectChoices[1]), $form->getData());
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

    /**
     * @group legacy
     */
    public function testSubmitMultipleExpandedNumericChoices()
    {
        $form = $this->factory->create('Symfony\Component\Form\Extension\Core\Type\ChoiceType', null, array(
            'multiple' => true,
            'expanded' => true,
            'choices' => $this->numericChoicesFlipped,
        ));

        $form->submit(array('1', '2'));

        $this->assertSame(array(1, 2), $form->getData());
        $this->assertTrue($form->isSynchronized());

        $this->assertFalse($form[0]->getData());
        $this->assertTrue($form[1]->getData());
        $this->assertTrue($form[2]->getData());
        $this->assertFalse($form[3]->getData());
        $this->assertFalse($form[4]->getData());
        $this->assertNull($form[0]->getViewData());
        $this->assertSame('1', $form[1]->getViewData());
        $this->assertSame('2', $form[2]->getViewData());
        $this->assertNull($form[3]->getViewData());
        $this->assertNull($form[4]->getViewData());
    }

    public function testSingleSelectedObjectChoices()
    {
        $form = $this->factory->create('Symfony\Component\Form\Extension\Core\Type\ChoiceType', $this->objectChoices[3], array(
            'multiple' => false,
            'expanded' => false,
            'choices' => $this->objectChoices,
            'choices_as_values' => true,
            'choice_label' => 'name',
            'choice_value' => 'id',
        ));

        $view = $form->createView();
        $selectedChecker = $view->vars['is_selected'];

        $this->assertTrue($selectedChecker($view->vars['choices'][3]->value, $view->vars['value']));
        $this->assertFalse($selectedChecker($view->vars['choices'][1]->value, $view->vars['value']));
    }

    public function testMultipleSelectedObjectChoices()
    {
        $form = $this->factory->create('Symfony\Component\Form\Extension\Core\Type\ChoiceType', array($this->objectChoices[3]), array(
            'multiple' => true,
            'expanded' => false,
            'choices' => $this->objectChoices,
            'choices_as_values' => true,
            'choice_label' => 'name',
            'choice_value' => 'id',
        ));

        $view = $form->createView();
        $selectedChecker = $view->vars['is_selected'];

        $this->assertTrue($selectedChecker($view->vars['choices'][3]->value, $view->vars['value']));
        $this->assertFalse($selectedChecker($view->vars['choices'][1]->value, $view->vars['value']));
    }

    /**
     * We need this functionality to create choice fields for Boolean types,
     * e.g. false => 'No', true => 'Yes'.
     *
     * @group legacy
     */
    public function testSetDataSingleNonExpandedAcceptsBoolean()
    {
        $form = $this->factory->create('Symfony\Component\Form\Extension\Core\Type\ChoiceType', null, array(
            'multiple' => false,
            'expanded' => false,
            'choices' => $this->numericChoicesFlipped,
        ));

        $form->setData(false);

        $this->assertFalse($form->getData());
        $this->assertEquals('0', $form->getViewData());
        $this->assertTrue($form->isSynchronized());
    }

    /**
     * @group legacy
     */
    public function testSetDataMultipleNonExpandedAcceptsBoolean()
    {
        $form = $this->factory->create('Symfony\Component\Form\Extension\Core\Type\ChoiceType', null, array(
            'multiple' => true,
            'expanded' => false,
            'choices' => $this->numericChoicesFlipped,
        ));

        $form->setData(array(false, true));

        $this->assertEquals(array(false, true), $form->getData());
        $this->assertEquals(array('0', '1'), $form->getViewData());
        $this->assertTrue($form->isSynchronized());
    }

    public function testPassRequiredToView()
    {
        $form = $this->factory->create('Symfony\Component\Form\Extension\Core\Type\ChoiceType', null, array(
            'choices' => $this->choices,
            'choices_as_values' => true,
        ));
        $view = $form->createView();

        $this->assertTrue($view->vars['required']);
    }

    public function testPassNonRequiredToView()
    {
        $form = $this->factory->create('Symfony\Component\Form\Extension\Core\Type\ChoiceType', null, array(
            'required' => false,
            'choices' => $this->choices,
            'choices_as_values' => true,
        ));
        $view = $form->createView();

        $this->assertFalse($view->vars['required']);
    }

    public function testPassMultipleToView()
    {
        $form = $this->factory->create('Symfony\Component\Form\Extension\Core\Type\ChoiceType', null, array(
            'multiple' => true,
            'choices' => $this->choices,
            'choices_as_values' => true,
        ));
        $view = $form->createView();

        $this->assertTrue($view->vars['multiple']);
    }

    public function testPassExpandedToView()
    {
        $form = $this->factory->create('Symfony\Component\Form\Extension\Core\Type\ChoiceType', null, array(
            'expanded' => true,
            'choices' => $this->choices,
            'choices_as_values' => true,
        ));
        $view = $form->createView();

        $this->assertTrue($view->vars['expanded']);
    }

    public function testPassChoiceTranslationDomainToView()
    {
        $form = $this->factory->create('Symfony\Component\Form\Extension\Core\Type\ChoiceType', null, array(
            'choices' => $this->choices,
            'choices_as_values' => true,
        ));
        $view = $form->createView();

        $this->assertNull($view->vars['choice_translation_domain']);
    }

    public function testChoiceTranslationDomainWithTrueValueToView()
    {
        $form = $this->factory->create('Symfony\Component\Form\Extension\Core\Type\ChoiceType', null, array(
            'choices' => $this->choices,
            'choices_as_values' => true,
            'choice_translation_domain' => true,
        ));
        $view = $form->createView();

        $this->assertNull($view->vars['choice_translation_domain']);
    }

    public function testDefaultChoiceTranslationDomainIsSameAsTranslationDomainToView()
    {
        $form = $this->factory->create('Symfony\Component\Form\Extension\Core\Type\ChoiceType', null, array(
            'choices' => $this->choices,
            'choices_as_values' => true,
            'translation_domain' => 'foo',
        ));
        $view = $form->createView();

        $this->assertEquals('foo', $view->vars['choice_translation_domain']);
    }

    public function testInheritChoiceTranslationDomainFromParent()
    {
        $view = $this->factory
            ->createNamedBuilder('parent', 'Symfony\Component\Form\Extension\Core\Type\FormType', null, array(
                'translation_domain' => 'domain',
            ))
            ->add('child', 'Symfony\Component\Form\Extension\Core\Type\ChoiceType', array(
                'choices' => array(),
                'choices_as_values' => true,
            ))
            ->getForm()
            ->createView();

        $this->assertEquals('domain', $view['child']->vars['choice_translation_domain']);
    }

    public function testPlaceholderIsNullByDefaultIfRequired()
    {
        $form = $this->factory->create('Symfony\Component\Form\Extension\Core\Type\ChoiceType', null, array(
            'multiple' => false,
            'required' => true,
            'choices' => $this->choices,
            'choices_as_values' => true,
        ));
        $view = $form->createView();

        $this->assertNull($view->vars['placeholder']);
    }

    public function testPlaceholderIsEmptyStringByDefaultIfNotRequired()
    {
        $form = $this->factory->create('Symfony\Component\Form\Extension\Core\Type\ChoiceType', null, array(
            'multiple' => false,
            'required' => false,
            'choices' => $this->choices,
            'choices_as_values' => true,
        ));
        $view = $form->createView();

        $this->assertSame('', $view->vars['placeholder']);
    }

    /**
     * @dataProvider getOptionsWithPlaceholder
     */
    public function testPassPlaceholderToView($multiple, $expanded, $required, $placeholder, $viewValue)
    {
        $form = $this->factory->create('Symfony\Component\Form\Extension\Core\Type\ChoiceType', null, array(
            'multiple' => $multiple,
            'expanded' => $expanded,
            'required' => $required,
            'placeholder' => $placeholder,
            'choices' => $this->choices,
            'choices_as_values' => true,
        ));
        $view = $form->createView();

        $this->assertSame($viewValue, $view->vars['placeholder']);
        $this->assertFalse($view->vars['placeholder_in_choices']);
    }

    /**
     * @dataProvider getOptionsWithPlaceholder
     * @group legacy
     */
    public function testPassEmptyValueBC($multiple, $expanded, $required, $placeholder, $viewValue)
    {
        $form = $this->factory->create('Symfony\Component\Form\Extension\Core\Type\ChoiceType', null, array(
            'multiple' => $multiple,
            'expanded' => $expanded,
            'required' => $required,
            'empty_value' => $placeholder,
            'choices' => $this->choices,
            'choices_as_values' => true,
        ));
        $view = $form->createView();

        $this->assertSame($viewValue, $view->vars['placeholder']);
        $this->assertFalse($view->vars['placeholder_in_choices']);
        $this->assertSame($viewValue, $view->vars['empty_value']);
        $this->assertFalse($view->vars['empty_value_in_choices']);
    }

    /**
     * @dataProvider getOptionsWithPlaceholder
     */
    public function testDontPassPlaceholderIfContainedInChoices($multiple, $expanded, $required, $placeholder, $viewValue)
    {
        $form = $this->factory->create('Symfony\Component\Form\Extension\Core\Type\ChoiceType', null, array(
            'multiple' => $multiple,
            'expanded' => $expanded,
            'required' => $required,
            'placeholder' => $placeholder,
            'choices' => array('Empty' => '', 'A' => 'a'),
            'choices_as_values' => true,
        ));
        $view = $form->createView();

        $this->assertNull($view->vars['placeholder']);
        $this->assertTrue($view->vars['placeholder_in_choices']);
    }

    public function getOptionsWithPlaceholder()
    {
        return array(
            // single non-expanded
            array(false, false, false, 'foobar', 'foobar'),
            array(false, false, false, '', ''),
            array(false, false, false, null, null),
            array(false, false, false, false, null),
            array(false, false, true, 'foobar', 'foobar'),
            array(false, false, true, '', ''),
            array(false, false, true, null, null),
            array(false, false, true, false, null),
            // single expanded
            array(false, true, false, 'foobar', 'foobar'),
            // radios should never have an empty label
            array(false, true, false, '', 'None'),
            array(false, true, false, null, null),
            array(false, true, false, false, null),
            // required radios should never have a placeholder
            array(false, true, true, 'foobar', null),
            array(false, true, true, '', null),
            array(false, true, true, null, null),
            array(false, true, true, false, null),
            // multiple non-expanded
            array(true, false, false, 'foobar', null),
            array(true, false, false, '', null),
            array(true, false, false, null, null),
            array(true, false, false, false, null),
            array(true, false, true, 'foobar', null),
            array(true, false, true, '', null),
            array(true, false, true, null, null),
            array(true, false, true, false, null),
            // multiple expanded
            array(true, true, false, 'foobar', null),
            array(true, true, false, '', null),
            array(true, true, false, null, null),
            array(true, true, false, false, null),
            array(true, true, true, 'foobar', null),
            array(true, true, true, '', null),
            array(true, true, true, null, null),
            array(true, true, true, false, null),
        );
    }

    /**
     * @dataProvider getOptionsWithPlaceholderAndEmptyValue
     * @group legacy
     */
    public function testPlaceholderOptionWithEmptyValueOption($multiple, $expanded, $required, $placeholder, $emptyValue, $viewValue)
    {
        $form = $this->factory->create('choice', null, array(
            'multiple' => $multiple,
            'expanded' => $expanded,
            'required' => $required,
            'placeholder' => $placeholder,
            'empty_value' => $emptyValue,
            'choices' => $this->choices,
        ));
        $view = $form->createView();

        $this->assertSame($viewValue, $view->vars['placeholder']);
        $this->assertFalse($view->vars['placeholder_in_choices']);
    }

    public function getOptionsWithPlaceholderAndEmptyValue()
    {
        return array(
            // single non-expanded, not required
            'A placeholder is not used if it is explicitly set to false' => array(false, false, false, false, false, null),
            'A placeholder is not used if it is explicitly set to false with null as empty value' => array(false, false, false, false, null, null),
            'A placeholder is not used if it is explicitly set to false with empty string as empty value' => array(false, false, false, false, '', null),
            'A placeholder is not used if it is explicitly set to false with "bar" as empty value' => array(false, false, false, false, 'bar', null),
            'A placeholder is not used if empty_value is set to false [maintains BC]' => array(false, false, false, null, false, null),
            'An unset empty_value is automatically made an empty string in a non-required field (but null is expected here) [maintains BC]' => array(false, false, false, null, null, null),
            'An empty string empty_value is used if placeholder is not set [maintains BC]' => array(false, false, false, null, '', ''),
            'A non-empty string empty_value is used if placeholder is not set [maintains BC]' => array(false, false, false, null, 'bar', 'bar'),
            'A placeholder is not used if it is an empty string and empty_value is set to false [maintains BC]' => array(false, false, false, '', false, null),
            'An unset empty_value is automatically made an empty string in a non-required field (but null is expected here) when placeholder is an empty string [maintains BC]' => array(false, false, false, '', null, null),
            'An empty string empty_value is used if placeholder is also an empty string [maintains BC]' => array(false, false, false, '', '', ''),
            'A non-empty string empty_value is used if placeholder is an empty string [maintains BC]' => array(false, false, false, '', 'bar', 'bar'),
            'A non-empty string placeholder takes precedence over an empty_value set to false' => array(false, false, false, 'foo', false, 'foo'),
            'A non-empty string placeholder takes precedence over a not set empty_value' => array(false, false, false, 'foo', null, 'foo'),
            'A non-empty string placeholder takes precedence over an empty string empty_value' => array(false, false, false, 'foo', '', 'foo'),
            'A non-empty string placeholder takes precedence over a non-empty string empty_value' => array(false, false, false, 'foo', 'bar', 'foo'),
            // single non-expanded, required
            'A placeholder is not used if it is explicitly set to false when required' => array(false, false, true, false, false, null),
            'A placeholder is not used if it is explicitly set to false with null as empty value when required' => array(false, false, true, false, null, null),
            'A placeholder is not used if it is explicitly set to false with empty string as empty value when required' => array(false, false, true, false, '', null),
            'A placeholder is not used if it is explicitly set to false with "bar" as empty value when required' => array(false, false, true, false, 'bar', null),
            'A placeholder is not used if empty_value is set to false when required [maintains BC]' => array(false, false, true, null, false, null),
            'A placeholder is not used if empty_value is not set when required [maintains BC]' => array(false, false, true, null, null, null),
            'An empty string empty_value is used if placeholder is not set when required [maintains BC]' => array(false, false, true, null, '', ''),
            'A non-empty string empty_value is used if placeholder is not set when required [maintains BC]' => array(false, false, true, null, 'bar', 'bar'),
            'A placeholder is not used if it is an empty string and empty_value is set to false when required [maintains BC]' => array(false, false, true, '', false, null),
            'A placeholder is not used if empty_value is not set [maintains BC]' => array(false, false, true, '', null, null),
            'An empty string empty_value is used if placeholder is also an empty string when required [maintains BC]' => array(false, false, true, '', '', ''),
            'A non-empty string empty_value is used if placeholder is an empty string when required [maintains BC]' => array(false, false, true, '', 'bar', 'bar'),
            'A non-empty string placeholder takes precedence over an empty_value set to false when required' => array(false, false, true, 'foo', false, 'foo'),
            'A non-empty string placeholder takes precedence over a not set empty_value' => array(false, false, true, 'foo', null, 'foo'),
            'A non-empty string placeholder takes precedence over an empty string empty_value when required' => array(false, false, true, 'foo', '', 'foo'),
            'A non-empty string placeholder takes precedence over a non-empty string empty_value when required' => array(false, false, true, 'foo', 'bar', 'foo'),
            // single expanded, not required
            'A placeholder is not used if it is explicitly set to false when expanded' => array(false, true, false, false, false, null),
            'A placeholder is not used if it is explicitly set to false with null as empty value when expanded' => array(false, true, false, false, null, null),
            'A placeholder is not used if it is explicitly set to false with empty string as empty value when expanded' => array(false, true, false, false, '', null),
            'A placeholder is not used if it is explicitly set to false with "bar" as empty value when expanded' => array(false, true, false, false, 'bar', null),
            'A placeholder is not used if empty_value is set to false when expanded [maintains BC]' => array(false, true, false, null, false, null),
            'An unset empty_value is automatically made an empty string in a non-required field when expanded (but null is expected here) [maintains BC]' => array(false, true, false, null, null, null),
            'An empty string empty_value is converted to "None" in an expanded single choice field [maintains BC]' => array(false, true, false, null, '', 'None'),
            'A non-empty string empty_value is used if placeholder is not set when expanded [maintains BC]' => array(false, true, false, null, 'bar', 'bar'),
            'A placeholder is not used if it is an empty string and empty_value is set to false when expanded [maintains BC]' => array(false, true, false, '', false, null),
            'An unset empty_value is automatically made an empty string in a non-required field (but null is expected here) when expanded [maintains BC]' => array(false, true, false, '', null, null),
            'An empty string empty_value is converted to "None" in an expanded single choice field when placeholder is an empty string [maintains BC]' => array(false, true, false, '', '', 'None'),
            'A non-empty string empty_value is used if placeholder is an empty string when expanded [maintains BC]' => array(false, true, false, '', 'bar', 'bar'),
            'A non-empty string placeholder takes precedence over an empty_value set to false when expanded' => array(false, true, false, 'foo', false, 'foo'),
            'A non-empty string placeholder takes precedence over a not set empty_value when expanded' => array(false, true, false, 'foo', null, 'foo'),
            'A non-empty string placeholder takes precedence over an empty string empty_value when expanded' => array(false, true, false, 'foo', '', 'foo'),
            'A non-empty string placeholder takes precedence over a non-empty string empty_value when expanded' => array(false, true, false, 'foo', 'bar', 'foo'),
            // single expanded, required
            'A placeholder is not used if it is explicitly set to false when expanded and required' => array(false, true, true, false, false, null),
            'A placeholder is not used if it is explicitly set to false with null as empty value when expanded and required' => array(false, true, true, false, null, null),
            'A placeholder is not used if it is explicitly set to false with empty string as empty value when expanded and required' => array(false, true, true, false, '', null),
            'A placeholder is not used if it is explicitly set to false with "bar" as empty value when expanded and required' => array(false, true, true, false, 'bar', null),
            'A placeholder is not used if empty_value is set to false when expanded and required [maintains BC]' => array(false, true, true, null, false, null),
            'A placeholder is not used if empty_value is not set when expanded and required [maintains BC]' => array(false, true, true, null, null, null),
            'An empty string empty_value is not used in an expanded single choice field when expanded and required [maintains BC]' => array(false, true, true, null, '', null),
            'A non-empty string empty_value is not used if placeholder is not set when expanded and required [maintains BC]' => array(false, true, true, null, 'bar', null),
            'A placeholder is not used if it is an empty string and empty_value is set to false when expanded and required [maintains BC]' => array(false, true, true, '', false, null),
            'A placeholder is not used as empty string if empty_value is not set when expanded and required [maintains BC]' => array(false, true, true, '', null, null),
            'An empty string empty_value is ignored in an expanded single choice field when required [maintains BC]' => array(false, true, true, 'foo', '', null),
            'A non-empty string empty_value is ignored when expanded and required [maintains BC]' => array(false, true, true, '', 'bar', null),
            'A non-empty string placeholder is ignored when expanded and required' => array(false, true, true, 'foo', '', null),
            // multiple expanded, not required
            array(true, true, false, false, false, null),
            array(true, true, false, false, null, null),
            array(true, true, false, false, '', null),
            array(true, true, false, false, 'bar', null),
            array(true, true, false, null, false, null),
            array(true, true, false, null, null, null),
            array(true, true, false, null, '', null),
            array(true, true, false, null, 'bar', null),
            array(true, true, false, '', false, null),
            array(true, true, false, '', null, null),
            array(true, true, false, '', '', null),
            array(true, true, false, '', 'bar', null),
            array(true, true, false, 'foo', false, null),
            array(true, true, false, 'foo', null, null),
            array(true, true, false, 'foo', '', null),
            array(true, true, false, 'foo', 'bar', null),
            // multiple expanded, required
            array(true, true, true, false, false, null),
            array(true, true, true, false, null, null),
            array(true, true, true, false, '', null),
            array(true, true, true, false, 'bar', null),
            array(true, true, true, null, false, null),
            array(true, true, true, null, null, null),
            array(true, true, true, null, '', null),
            array(true, true, true, null, 'bar', null),
            array(true, true, true, '', false, null),
            array(true, true, true, '', null, null),
            array(true, true, true, '', '', null),
            array(true, true, true, '', 'bar', null),
            array(true, true, true, 'foo', false, null),
            array(true, true, true, 'foo', null, null),
            array(true, true, true, 'foo', '', null),
            array(true, true, true, 'foo', 'bar', null),
        );
    }

    public function testPassChoicesToView()
    {
        $choices = array('A' => 'a', 'B' => 'b', 'C' => 'c', 'D' => 'd');
        $form = $this->factory->create('Symfony\Component\Form\Extension\Core\Type\ChoiceType', null, array(
            'choices' => $choices,
            'choices_as_values' => true,
        ));
        $view = $form->createView();

        $this->assertEquals(array(
            new ChoiceView('a', 'a', 'A'),
            new ChoiceView('b', 'b', 'B'),
            new ChoiceView('c', 'c', 'C'),
            new ChoiceView('d', 'd', 'D'),
        ), $view->vars['choices']);
    }

    public function testPassPreferredChoicesToView()
    {
        $choices = array('A' => 'a', 'B' => 'b', 'C' => 'c', 'D' => 'd');
        $form = $this->factory->create('Symfony\Component\Form\Extension\Core\Type\ChoiceType', null, array(
            'choices' => $choices,
            'choices_as_values' => true,
            'preferred_choices' => array('b', 'd'),
        ));
        $view = $form->createView();

        $this->assertEquals(array(
            0 => new ChoiceView('a', 'a', 'A'),
            2 => new ChoiceView('c', 'c', 'C'),
        ), $view->vars['choices']);
        $this->assertEquals(array(
            1 => new ChoiceView('b', 'b', 'B'),
            3 => new ChoiceView('d', 'd', 'D'),
        ), $view->vars['preferred_choices']);
    }

    public function testPassHierarchicalChoicesToView()
    {
        $form = $this->factory->create('Symfony\Component\Form\Extension\Core\Type\ChoiceType', null, array(
            'choices' => $this->groupedChoices,
            'choices_as_values' => true,
            'preferred_choices' => array('b', 'd'),
        ));
        $view = $form->createView();

        $this->assertEquals(array(
            'Symfony' => new ChoiceGroupView('Symfony', array(
                0 => new ChoiceView('a', 'a', 'Bernhard'),
                2 => new ChoiceView('c', 'c', 'Kris'),
            )),
            'Doctrine' => new ChoiceGroupView('Doctrine', array(
                4 => new ChoiceView('e', 'e', 'Roman'),
            )),
        ), $view->vars['choices']);
        $this->assertEquals(array(
            'Symfony' => new ChoiceGroupView('Symfony', array(
                1 => new ChoiceView('b', 'b', 'Fabien'),
            )),
            'Doctrine' => new ChoiceGroupView('Doctrine', array(
                3 => new ChoiceView('d', 'd', 'Jon'),
            )),
        ), $view->vars['preferred_choices']);
    }

    public function testPassChoiceDataToView()
    {
        $obj1 = (object) array('value' => 'a', 'label' => 'A');
        $obj2 = (object) array('value' => 'b', 'label' => 'B');
        $obj3 = (object) array('value' => 'c', 'label' => 'C');
        $obj4 = (object) array('value' => 'd', 'label' => 'D');
        $form = $this->factory->create('Symfony\Component\Form\Extension\Core\Type\ChoiceType', null, array(
            'choices' => array($obj1, $obj2, $obj3, $obj4),
            'choices_as_values' => true,
            'choice_label' => 'label',
            'choice_value' => 'value',
        ));
        $view = $form->createView();

        $this->assertEquals(array(
            new ChoiceView($obj1, 'a', 'A'),
            new ChoiceView($obj2, 'b', 'B'),
            new ChoiceView($obj3, 'c', 'C'),
            new ChoiceView($obj4, 'd', 'D'),
        ), $view->vars['choices']);
    }

    /**
     * @group legacy
     */
    public function testDuplicateChoiceLabels()
    {
        $form = $this->factory->create('choice', null, array(
            'choices' => array('a' => 'A', 'b' => 'B', 'c' => 'A'),
        ));
        $view = $form->createView();

        $this->assertEquals(array(
            new ChoiceView('a', 'a', 'A'),
            new ChoiceView('b', 'b', 'B'),
            new ChoiceView('c', 'c', 'A'),
        ), $view->vars['choices']);
    }

    public function testAdjustFullNameForMultipleNonExpanded()
    {
        $form = $this->factory->createNamed('name', 'Symfony\Component\Form\Extension\Core\Type\ChoiceType', null, array(
            'multiple' => true,
            'expanded' => false,
            'choices' => $this->choices,
            'choices_as_values' => true,
        ));
        $view = $form->createView();

        $this->assertSame('name[]', $view->vars['full_name']);
    }

    // https://github.com/symfony/symfony/issues/3298
    public function testInitializeWithEmptyChoices()
    {
        $this->factory->createNamed('name', 'Symfony\Component\Form\Extension\Core\Type\ChoiceType', null, array(
            'choices' => array(),
            'choices_as_values' => true,
        ));
    }

    public function testInitializeWithDefaultObjectChoice()
    {
        $obj1 = (object) array('value' => 'a', 'label' => 'A');
        $obj2 = (object) array('value' => 'b', 'label' => 'B');
        $obj3 = (object) array('value' => 'c', 'label' => 'C');
        $obj4 = (object) array('value' => 'd', 'label' => 'D');

        $form = $this->factory->create('Symfony\Component\Form\Extension\Core\Type\ChoiceType', null, array(
            'choices' => array($obj1, $obj2, $obj3, $obj4),
            'choices_as_values' => true,
            'choice_label' => 'label',
            'choice_value' => 'value',
            // Used to break because "data_class" was inferred, which needs to
            // remain null in every case (because it refers to the view format)
            'data' => $obj3,
        ));

        // Trigger data initialization
        $form->getViewData();
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
        $builder->add('choice', 'Symfony\Component\Form\Extension\Core\Type\ChoiceType', array(
                'choices' => array(
                    '1' => '1',
                    '2' => '2',
                ),
                'choices_as_values' => true,
            )
        );
        $builder->add('subChoice', 'Symfony\Component\Form\Tests\Fixtures\ChoiceSubType');
        $form = $builder->getForm();

        // The default 'choices' normalizer would fill the $choiceLabels, but it has been replaced
        // in the custom choice type, so $choiceLabels->labels remains empty array.
        // In this case the 'choice_label' closure returns null and not the closure from the first choice type.
        $this->assertNull($form->get('subChoice')->getConfig()->getOption('choice_label'));
    }
}
