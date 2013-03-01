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

use Symfony\Component\Form\Extension\Core\ChoiceList\ChoiceList;

use Symfony\Component\Form\Extension\Core\ChoiceList\ObjectChoiceList;
use Symfony\Component\Form\Extension\Core\View\ChoiceView;

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

    private $objectChoices;

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
     * @expectedException \PHPUnit_Framework_Error
     */
    public function testChoicesOptionExpectsArray()
    {
        $this->factory->create('choice', null, array(
            'choices' => new \ArrayObject(),
        ));
    }

    /**
     * @expectedException Symfony\Component\OptionsResolver\Exception\InvalidOptionsException
     */
    public function testChoiceListOptionExpectsChoiceListInterface()
    {
        $this->factory->create('choice', null, array(
            'choice_list' => array('foo' => 'foo'),
        ));
    }

    /**
     * expectedException Symfony\Component\Form\Exception\FormException
     */
    public function testEitherChoiceListOrChoicesMustBeSet()
    {
        $this->factory->create('choice', null, array(
        ));
    }

    public function testExpandedChoicesOptionsTurnIntoChildren()
    {
        $form = $this->factory->create('choice', null, array(
            'expanded'  => true,
            'choices'   => $this->choices,
        ));

        $this->assertCount($form->count(), $this->choices, 'Each choice should become a new field');
    }

    public function testExpandedChoicesOptionsAreFlattened()
    {
        $form = $this->factory->create('choice', null, array(
            'expanded'  => true,
            'choices'   => $this->groupedChoices,
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

    public function testExpandedRadiosAreRequiredIfChoiceChildIsRequired()
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

    public function testExpandedRadiosAreNotRequiredIfChoiceChildIsNotRequired()
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
        $this->assertEquals('b', $form->getViewData());
    }

    public function testBindSingleNonExpandedObjectChoices()
    {
        $form = $this->factory->create('choice', null, array(
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
        $form->bind('2');

        $this->assertEquals($this->objectChoices[1], $form->getData());
        $this->assertEquals('2', $form->getViewData());
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
        $this->assertEquals(array('a', 'b'), $form->getViewData());
    }

    public function testBindMultipleNonExpandedObjectChoices()
    {
        $form = $this->factory->create('choice', null, array(
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

        $form->bind(array('2', '3'));

        $this->assertEquals(array($this->objectChoices[1], $this->objectChoices[2]), $form->getData());
        $this->assertEquals(array('2', '3'), $form->getViewData());
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

    public function testBindSingleExpandedNothingChecked()
    {
        $form = $this->factory->create('choice', null, array(
            'multiple' => false,
            'expanded' => true,
            'choices' => $this->choices,
        ));

        $form->bind(null);

        $this->assertNull($form->getData());
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

    public function testBindSingleExpandedWithFalseDoesNotHaveExtraChildren()
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

    public function testBindSingleExpandedWithEmptyChild()
    {
        $form = $this->factory->create('choice', null, array(
            'multiple' => false,
            'expanded' => true,
            'choices' => array(
                '' => 'Empty',
                1 => 'Not empty',
            ),
        ));

        $form->bind('');

        $this->assertNull($form->getData());
        $this->assertTrue($form[0]->getData());
        $this->assertFalse($form[1]->getData());
        $this->assertSame('', $form[0]->getViewData());
        $this->assertNull($form[1]->getViewData());
    }

    public function testBindSingleExpandedObjectChoices()
    {
        $form = $this->factory->create('choice', null, array(
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

        $form->bind('2');

        $this->assertSame($this->objectChoices[1], $form->getData());
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

    public function testBindSingleExpandedNumericChoices()
    {
        $form = $this->factory->create('choice', null, array(
            'multiple' => false,
            'expanded' => true,
            'choices' => $this->numericChoices,
        ));

        $form->bind('1');

        $this->assertSame(1, $form->getData());
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

    public function testBindMultipleExpanded()
    {
        $form = $this->factory->create('choice', null, array(
            'multiple' => true,
            'expanded' => true,
            'choices' => $this->choices,
        ));

        $form->bind(array('a', 'c'));

        $this->assertSame(array('a', 'c'), $form->getData());
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

    public function testBindMultipleExpandedEmpty()
    {
        $form = $this->factory->create('choice', null, array(
            'multiple' => true,
            'expanded' => true,
            'choices' => $this->choices,
        ));

        $form->bind(array());

        $this->assertSame(array(), $form->getData());
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

    public function testBindMultipleExpandedWithEmptyChild()
    {
        $form = $this->factory->create('choice', null, array(
            'multiple' => true,
            'expanded' => true,
            'choices' => array(
                '' => 'Empty',
                1 => 'Not Empty',
                2 => 'Not Empty 2',
            )
        ));

        $form->bind(array('', '2'));

        $this->assertSame(array('', 2), $form->getData());
        $this->assertTrue($form[0]->getData());
        $this->assertFalse($form[1]->getData());
        $this->assertTrue($form[2]->getData());
        $this->assertSame('', $form[0]->getViewData());
        $this->assertNull($form[1]->getViewData());
        $this->assertSame('2', $form[2]->getViewData());
    }

    public function testBindMultipleExpandedObjectChoices()
    {
        $form = $this->factory->create('choice', null, array(
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

        $form->bind(array('1', '2'));

        $this->assertSame(array($this->objectChoices[0], $this->objectChoices[1]), $form->getData());
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

    public function testBindMultipleExpandedNumericChoices()
    {
        $form = $this->factory->create('choice', null, array(
            'multiple' => true,
            'expanded' => true,
            'choices' => $this->numericChoices,
        ));

        $form->bind(array('1', '2'));

        $this->assertSame(array(1, 2), $form->getData());
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

        $this->assertFalse($form->getData());
        $this->assertEquals('0', $form->getViewData());
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
        $this->assertEquals(array('0', '1'), $form->getViewData());
    }

    public function testPassRequiredToView()
    {
        $form = $this->factory->create('choice', null, array(
            'choices' => $this->choices,
        ));
        $view = $form->createView();

        $this->assertTrue($view->vars['required']);
    }

    public function testPassNonRequiredToView()
    {
        $form = $this->factory->create('choice', null, array(
            'required' => false,
            'choices' => $this->choices,
        ));
        $view = $form->createView();

        $this->assertFalse($view->vars['required']);
    }

    public function testPassMultipleToView()
    {
        $form = $this->factory->create('choice', null, array(
            'multiple' => true,
            'choices' => $this->choices,
        ));
        $view = $form->createView();

        $this->assertTrue($view->vars['multiple']);
    }

    public function testPassExpandedToView()
    {
        $form = $this->factory->create('choice', null, array(
            'expanded' => true,
            'choices' => $this->choices,
        ));
        $view = $form->createView();

        $this->assertTrue($view->vars['expanded']);
    }

    public function testNotPassedEmptyValueToViewIsNull()
    {
        $form = $this->factory->create('choice', null, array(
            'multiple' => false,
            'choices' => $this->choices,
        ));
        $view = $form->createView();

        $this->assertNull($view->vars['empty_value']);
    }

    public function testPassEmptyValueToViewIsEmpty()
    {
        $form = $this->factory->create('choice', null, array(
            'multiple' => false,
            'required' => false,
            'choices' => $this->choices,
        ));
        $view = $form->createView();

        $this->assertEmpty($view->vars['empty_value']);
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

        $this->assertEquals($viewValue, $view->vars['empty_value']);
    }

    /**
     * @dataProvider getOptionsWithEmptyValue
     */
    public function testDontPassEmptyValueIfContainedInChoices($multiple, $expanded, $required, $emptyValue, $viewValue)
    {
        $form = $this->factory->create('choice', null, array(
            'multiple' => $multiple,
            'expanded' => $expanded,
            'required' => $required,
            'empty_value' => $emptyValue,
            'choices' => array('a' => 'A', '' => 'Empty'),
        ));
        $view = $form->createView();

        $this->assertNull($view->vars['empty_value']);
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

        $this->assertEquals(array(
            new ChoiceView('a', 'a', 'A'),
            new ChoiceView('b', 'b', 'B'),
            new ChoiceView('c', 'c', 'C'),
            new ChoiceView('d', 'd', 'D'),
        ), $view->vars['choices']);
    }

    public function testPassPreferredChoicesToView()
    {
        $choices = array('a' => 'A', 'b' => 'B', 'c' => 'C', 'd' => 'D');
        $form = $this->factory->create('choice', null, array(
            'choices' => $choices,
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
        $form = $this->factory->create('choice', null, array(
            'choices' => $this->groupedChoices,
            'preferred_choices' => array('b', 'd'),
        ));
        $view = $form->createView();

        $this->assertEquals(array(
            'Symfony' => array(
                0 => new ChoiceView('a', 'a', 'Bernhard'),
                2 => new ChoiceView('c', 'c', 'Kris'),
            ),
            'Doctrine' => array(
                4 => new ChoiceView('e', 'e', 'Roman'),
            ),
        ), $view->vars['choices']);
        $this->assertEquals(array(
            'Symfony' => array(
                1 => new ChoiceView('b', 'b', 'Fabien'),
            ),
            'Doctrine' => array(
                3 => new ChoiceView('d', 'd', 'Jon'),
            ),
        ), $view->vars['preferred_choices']);
    }

    public function testPassChoiceDataToView()
    {
        $obj1 = (object) array('value' => 'a', 'label' => 'A');
        $obj2 = (object) array('value' => 'b', 'label' => 'B');
        $obj3 = (object) array('value' => 'c', 'label' => 'C');
        $obj4 = (object) array('value' => 'd', 'label' => 'D');
        $form = $this->factory->create('choice', null, array(
            'choice_list' => new ObjectChoiceList(array($obj1, $obj2, $obj3, $obj4), 'label', array(), null, 'value'),
        ));
        $view = $form->createView();

        $this->assertEquals(array(
            new ChoiceView($obj1, 'a', 'A'),
            new ChoiceView($obj2, 'b', 'B'),
            new ChoiceView($obj3, 'c', 'C'),
            new ChoiceView($obj4, 'd', 'D'),
        ), $view->vars['choices']);
    }

    public function testAdjustFullNameForMultipleNonExpanded()
    {
        $form = $this->factory->createNamed('name', 'choice', null, array(
            'multiple' => true,
            'expanded' => false,
            'choices' => $this->choices,
        ));
        $view = $form->createView();

        $this->assertSame('name[]', $view->vars['full_name']);
    }

    // https://github.com/symfony/symfony/issues/3298
    public function testInitializeWithEmptyChoices()
    {
        $this->factory->createNamed('name', 'choice', null, array(
            'choices' => array(),
        ));
    }

    public function testInitializeWithDefaultObjectChoice()
    {
        $obj1 = (object) array('value' => 'a', 'label' => 'A');
        $obj2 = (object) array('value' => 'b', 'label' => 'B');
        $obj3 = (object) array('value' => 'c', 'label' => 'C');
        $obj4 = (object) array('value' => 'd', 'label' => 'D');

        $form = $this->factory->create('choice', null, array(
            'choice_list' => new ObjectChoiceList(array($obj1, $obj2, $obj3, $obj4), 'label', array(), null, 'value'),
            // Used to break because "data_class" was inferred, which needs to
            // remain null in every case (because it refers to the view format)
            'data' => $obj3,
        ));

        // Trigger data initialization
        $form->getViewData();
    }
}
