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

    /**
     * expectedException Symfony\Component\Form\Exception\FormException
     */
    public function testEitherChoiceListOrChoicesMustBeSet()
    {
        $form = $this->factory->create('choice', null, array(
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
        $this->assertEquals('b', $form->getClientData());
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
        $this->assertEquals('2', $form->getClientData());
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
        $this->assertEquals(array('2', '3'), $form->getClientData());
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
        $this->assertNull($form[0]->getClientData());
        $this->assertSame('b', $form[1]->getClientData());
        $this->assertNull($form[2]->getClientData());
        $this->assertNull($form[3]->getClientData());
        $this->assertNull($form[4]->getClientData());
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
        $this->assertNull($form[0]->getClientData());
        $this->assertNull($form[1]->getClientData());
        $this->assertNull($form[2]->getClientData());
        $this->assertNull($form[3]->getClientData());
        $this->assertNull($form[4]->getClientData());
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
        $this->assertSame('', $form[0]->getClientData());
        $this->assertNull($form[1]->getClientData());
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
        $this->assertNull($form[0]->getClientData());
        $this->assertSame('2', $form[1]->getClientData());
        $this->assertNull($form[2]->getClientData());
        $this->assertNull($form[3]->getClientData());
        $this->assertNull($form[4]->getClientData());
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
        $this->assertNull($form[0]->getClientData());
        $this->assertSame('1', $form[1]->getClientData());
        $this->assertNull($form[2]->getClientData());
        $this->assertNull($form[3]->getClientData());
        $this->assertNull($form[4]->getClientData());
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
        $this->assertSame('a', $form[0]->getClientData());
        $this->assertNull($form[1]->getClientData());
        $this->assertSame('c', $form[2]->getClientData());
        $this->assertNull($form[3]->getClientData());
        $this->assertNull($form[4]->getClientData());
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
        $this->assertNull($form[0]->getClientData());
        $this->assertNull($form[1]->getClientData());
        $this->assertNull($form[2]->getClientData());
        $this->assertNull($form[3]->getClientData());
        $this->assertNull($form[4]->getClientData());
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
        $this->assertSame('', $form[0]->getClientData());
        $this->assertNull($form[1]->getClientData());
        $this->assertSame('2', $form[2]->getClientData());
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
        $this->assertSame('1', $form[0]->getClientData());
        $this->assertSame('2', $form[1]->getClientData());
        $this->assertNull($form[2]->getClientData());
        $this->assertNull($form[3]->getClientData());
        $this->assertNull($form[4]->getClientData());
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
        $this->assertNull($form[0]->getClientData());
        $this->assertSame('1', $form[1]->getClientData());
        $this->assertSame('2', $form[2]->getClientData());
        $this->assertNull($form[3]->getClientData());
        $this->assertNull($form[4]->getClientData());
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

        $this->assertEquals(array(
            new ChoiceView('a', 'A'),
            new ChoiceView('b', 'B'),
            new ChoiceView('c', 'C'),
            new ChoiceView('d', 'D'),
        ), $view->get('choices'));
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
            0 => new ChoiceView('a', 'A'),
            2 => new ChoiceView('c', 'C'),
        ), $view->get('choices'));
        $this->assertEquals(array(
            1 => new ChoiceView('b', 'B'),
            3 => new ChoiceView('d', 'D'),
        ), $view->get('preferred_choices'));
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
                0 => new ChoiceView('a', 'Bernhard'),
                2 => new ChoiceView('c', 'Kris'),
            ),
            'Doctrine' => array(
                4 => new ChoiceView('e', 'Roman'),
            ),
        ), $view->get('choices'));
        $this->assertEquals(array(
            'Symfony' => array(
                1 => new ChoiceView('b', 'Fabien'),
            ),
            'Doctrine' => array(
                3 => new ChoiceView('d', 'Jon'),
            ),
        ), $view->get('preferred_choices'));
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

    // https://github.com/symfony/symfony/issues/3298
    public function testInitializeWithEmptyChoices()
    {
        $this->factory->createNamed('choice', 'name', null, array(
            'choices' => array(),
        ));
    }
}
