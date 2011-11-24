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

use Symfony\Component\Form\Exception\UnexpectedTypeException;

class ChoiceTypeTest extends TypeTestCase
{
    private $choices = array(
        'a' => 'Bernhard',
        'b' => 'Fabien',
        'c' => 'Kris',
        'd' => 'Jon',
        'e' => 'Roman',
    );

    private $numericChoices = array(
        0 => 'Bernhard',
        1 => 'Fabien',
        2 => 'Kris',
        3 => 'Jon',
        4 => 'Roman',
    );

    private $stringButNumericChoices = array(
        '0' => 'Bernhard',
        '1' => 'Fabien',
        '2' => 'Kris',
        '3' => 'Jon',
        '4' => 'Roman',
    );

    protected $groupedChoices = array(
        'Symfony' => array(
            'a' => 'Bernhard',
            'b' => 'Fabien',
            'c' => 'Kris',
        ),
        'Doctrine' => array(
            'd' => 'Jon',
            'e' => 'Roman',
        )
    );

    /**
     * @expectedException Symfony\Component\Form\Exception\UnexpectedTypeException
     */
    public function testChoicesOptionExpectsArray()
    {
        $form = $this->factory->create('choice', null, array(
            'choices' => new \ArrayObject(),
        ));
    }

    /**
     * @expectedException Symfony\Component\Form\Exception\FormException
     */
    public function testChoiceListOptionExpectsChoiceListInterface()
    {
        $form = $this->factory->create('choice', null, array(
            'choice_list' => array('foo' => 'foo'),
        ));
    }

    public function testExpandedCheckboxesAreNeverRequired()
    {
        $form = $this->factory->create('choice', null, array(
            'multiple' => true,
            'expanded' => true,
            'required' => true,
            'choices' => $this->choices,
        ));

        foreach ($form as $child) {
            $this->assertFalse($child->isRequired());
        }
    }

    public function testExpandedRadiosAreRequiredIfChoiceFieldIsRequired()
    {
        $form = $this->factory->create('choice', null, array(
            'multiple' => false,
            'expanded' => true,
            'required' => true,
            'choices' => $this->choices,
        ));

        foreach ($form as $child) {
            $this->assertTrue($child->isRequired());
        }
    }

    public function testExpandedRadiosAreNotRequiredIfChoiceFieldIsNotRequired()
    {
        $form = $this->factory->create('choice', null, array(
            'multiple' => false,
            'expanded' => true,
            'required' => false,
            'choices' => $this->choices,
        ));

        foreach ($form as $child) {
            $this->assertFalse($child->isRequired());
        }
    }

    public function testBindSingleNonExpanded()
    {
        $form = $this->factory->create('choice', null, array(
            'multiple' => false,
            'expanded' => false,
            'choices' => $this->choices,
        ));

        $form->bind('b');

        $this->assertEquals('b', $form->getData());
        $this->assertEquals('b', $form->getClientData());
    }

    public function testBindMultipleNonExpanded()
    {
        $form = $this->factory->create('choice', null, array(
            'multiple' => true,
            'expanded' => false,
            'choices' => $this->choices,
        ));

        $form->bind(array('a', 'b'));

        $this->assertEquals(array('a', 'b'), $form->getData());
        $this->assertEquals(array('a', 'b'), $form->getClientData());
    }

    public function testBindSingleExpanded()
    {
        $form = $this->factory->create('choice', null, array(
            'multiple' => false,
            'expanded' => true,
            'choices' => $this->choices,
        ));

        $form->bind('b');

        $this->assertSame('b', $form->getData());
        $this->assertSame(false, $form['a']->getData());
        $this->assertSame(true, $form['b']->getData());
        $this->assertSame(false, $form['c']->getData());
        $this->assertSame(false, $form['d']->getData());
        $this->assertSame(false, $form['e']->getData());
        $this->assertSame('', $form['a']->getClientData());
        $this->assertSame('1', $form['b']->getClientData());
        $this->assertSame('', $form['c']->getClientData());
        $this->assertSame('', $form['d']->getClientData());
        $this->assertSame('', $form['e']->getClientData());
    }

    public function testBindSingleExpandedWithFalseDoesNotHaveExtraFields()
    {
        $form = $this->factory->create('choice', null, array(
            'multiple' => false,
            'expanded' => true,
            'choices' => $this->choices,
        ));

        $form->bind(false);

        $this->assertEmpty($form->getExtraData());
        $this->assertNull($form->getData());
    }

    public function testBindSingleExpandedNumericChoices()
    {
        $form = $this->factory->create('choice', null, array(
            'multiple' => false,
            'expanded' => true,
            'choices' => $this->numericChoices,
        ));

        $form->bind('1');

        $this->assertSame('1', $form->getData());
        $this->assertSame(false, $form[0]->getData());
        $this->assertSame(true, $form[1]->getData());
        $this->assertSame(false, $form[2]->getData());
        $this->assertSame(false, $form[3]->getData());
        $this->assertSame(false, $form[4]->getData());
        $this->assertSame('', $form[0]->getClientData());
        $this->assertSame('1', $form[1]->getClientData());
        $this->assertSame('', $form[2]->getClientData());
        $this->assertSame('', $form[3]->getClientData());
        $this->assertSame('', $form[4]->getClientData());
    }

    public function testBindSingleExpandedStringsButNumericChoices()
    {
        $form = $this->factory->create('choice', null, array(
            'multiple' => false,
            'expanded' => true,
            'choices' => $this->stringButNumericChoices,
        ));

        $form->bind('1');

        $this->assertSame('1', $form->getData());
        $this->assertSame(false, $form[0]->getData());
        $this->assertSame(true, $form[1]->getData());
        $this->assertSame(false, $form[2]->getData());
        $this->assertSame(false, $form[3]->getData());
        $this->assertSame(false, $form[4]->getData());
        $this->assertSame('', $form[0]->getClientData());
        $this->assertSame('1', $form[1]->getClientData());
        $this->assertSame('', $form[2]->getClientData());
        $this->assertSame('', $form[3]->getClientData());
        $this->assertSame('', $form[4]->getClientData());
    }

    public function testBindMultipleExpanded()
    {
        $form = $this->factory->create('choice', null, array(
            'multiple' => true,
            'expanded' => true,
            'choices' => $this->choices,
        ));

        $form->bind(array('a' => 'a', 'b' => 'b'));

        $this->assertSame(array('a', 'b'), $form->getData());
        $this->assertSame(true, $form['a']->getData());
        $this->assertSame(true, $form['b']->getData());
        $this->assertSame(false, $form['c']->getData());
        $this->assertSame(false, $form['d']->getData());
        $this->assertSame(false, $form['e']->getData());
        $this->assertSame('1', $form['a']->getClientData());
        $this->assertSame('1', $form['b']->getClientData());
        $this->assertSame('', $form['c']->getClientData());
        $this->assertSame('', $form['d']->getClientData());
        $this->assertSame('', $form['e']->getClientData());
    }

    public function testBindMultipleExpandedNumericChoices()
    {
        $form = $this->factory->create('choice', null, array(
            'multiple' => true,
            'expanded' => true,
            'choices' => $this->numericChoices,
        ));

        $form->bind(array(1 => 1, 2 => 2));

        $this->assertSame(array(1, 2), $form->getData());
        $this->assertSame(false, $form[0]->getData());
        $this->assertSame(true, $form[1]->getData());
        $this->assertSame(true, $form[2]->getData());
        $this->assertSame(false, $form[3]->getData());
        $this->assertSame(false, $form[4]->getData());
        $this->assertSame('', $form[0]->getClientData());
        $this->assertSame('1', $form[1]->getClientData());
        $this->assertSame('1', $form[2]->getClientData());
        $this->assertSame('', $form[3]->getClientData());
        $this->assertSame('', $form[4]->getClientData());
    }

    /*
     * We need this functionality to create choice fields for Boolean types,
     * e.g. false => 'No', true => 'Yes'
     */
    public function testSetDataSingleNonExpandedAcceptsBoolean()
    {
        $form = $this->factory->create('choice', null, array(
            'multiple' => false,
            'expanded' => false,
            'choices' => $this->numericChoices,
        ));

        $form->setData(false);

        $this->assertEquals(false, $form->getData());
        $this->assertEquals('0', $form->getClientData());
    }

    public function testSetDataMultipleNonExpandedAcceptsBoolean()
    {
        $form = $this->factory->create('choice', null, array(
            'multiple' => true,
            'expanded' => false,
            'choices' => $this->numericChoices,
        ));

        $form->setData(array(false, true));

        $this->assertEquals(array(false, true), $form->getData());
        $this->assertEquals(array('0', '1'), $form->getClientData());
    }

    public function testPassRequiredToView()
    {
        $form = $this->factory->create('choice', null, array(
            'choices' => $this->choices,
        ));
        $view = $form->createView();

        $this->assertTrue($view->get('required'));
    }

    public function testPassNonRequiredToView()
    {
        $form = $this->factory->create('choice', null, array(
            'required' => false,
            'choices' => $this->choices,
        ));
        $view = $form->createView();

        $this->assertFalse($view->get('required'));
    }

    public function testPassMultipleToView()
    {
        $form = $this->factory->create('choice', null, array(
            'multiple' => true,
            'choices' => $this->choices,
        ));
        $view = $form->createView();

        $this->assertTrue($view->get('multiple'));
    }

    public function testPassExpandedToView()
    {
        $form = $this->factory->create('choice', null, array(
            'expanded' => true,
            'choices' => $this->choices,
        ));
        $view = $form->createView();

        $this->assertTrue($view->get('expanded'));
    }

    public function testNotPassedEmptyValueToViewIsNull()
    {
        $form = $this->factory->create('choice', null, array(
            'multiple' => false,
            'choices' => $this->choices,
        ));
        $view = $form->createView();

        $this->assertNull($view->get('empty_value'));
    }

    public function testPassEmptyValueToViewIsEmpty()
    {
        $form = $this->factory->create('choice', null, array(
            'multiple' => false,
            'required' => false,
            'choices' => $this->choices,
        ));
        $view = $form->createView();

        $this->assertEmpty($view->get('empty_value'));
    }

    /**
     * @dataProvider getOptionsWithEmptyValue
     */
    public function testPassEmptyValueToView($multiple, $expanded, $required, $emptyValue, $viewValue)
    {
        $form = $this->factory->create('choice', null, array(
            'multiple' => $multiple,
            'expanded' => $expanded,
            'required' => $required,
            'empty_value' => $emptyValue,
            'choices' => $this->choices,
        ));
        $view = $form->createView();

        $this->assertEquals($viewValue, $view->get('empty_value'));
    }

    public function getOptionsWithEmptyValue()
    {
        return array(
            array(false, false, false, 'foobar', 'foobar'),
            array(true, false, false, 'foobar', null),
            array(false, true, false, 'foobar', null),
            array(false, false, true, 'foobar', 'foobar'),
            array(false, false, true, '', ''),
            array(false, false, true, null, null),
            array(false, true, true, 'foobar', null),
            array(true, true, false, 'foobar', null),
            array(true, true, true, 'foobar', null),
        );
    }

    public function testPassChoicesToView()
    {
        $choices = array('a' => 'A', 'b' => 'B', 'c' => 'C', 'd' => 'D');
        $form = $this->factory->create('choice', null, array(
            'choices' => $choices,
        ));
        $view = $form->createView();

        $this->assertSame($choices, $view->get('choices'));
    }

    public function testPassPreferredChoicesToView()
    {
        $choices = array('a' => 'A', 'b' => 'B', 'c' => 'C', 'd' => 'D');
        $form = $this->factory->create('choice', null, array(
            'choices' => $choices,
            'preferred_choices' => array('b', 'd'),
        ));
        $view = $form->createView();

        $this->assertSame(array('a' => 'A', 'c' => 'C'), $view->get('choices'));
        $this->assertSame(array('b' => 'B', 'd' => 'D'), $view->get('preferred_choices'));
    }

    public function testAdjustFullNameForMultipleNonExpanded()
    {
        $form = $this->factory->createNamed('choice', 'name', null, array(
            'multiple' => true,
            'expanded' => false,
            'choices' => $this->choices,
        ));
        $view = $form->createView();

        $this->assertSame('name[]', $view->get('full_name'));
    }
}
