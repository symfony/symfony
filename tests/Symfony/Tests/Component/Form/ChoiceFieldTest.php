<?php

namespace Symfony\Tests\Component\Form;

use Symfony\Component\Form\ChoiceField;
use Symfony\Component\Form\Exception\UnexpectedTypeException;

class ChoiceFieldTest extends \PHPUnit_Framework_TestCase
{
    protected $choices = array(
        'a' => 'Bernhard',
        'b' => 'Fabien',
        'c' => 'Kris',
        'd' => 'Jon',
        'e' => 'Roman',
    );

    protected $preferredChoices = array('d', 'e');

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

    protected $numericChoices = array(
        0 => 'Bernhard',
        1 => 'Fabien',
        2 => 'Kris',
        3 => 'Jon',
        4 => 'Roman',
    );

    /**
     * @expectedException Symfony\Component\Form\Exception\InvalidOptionsException
     */
    public function testConfigureChoicesWithNonArray()
    {
        $field = new ChoiceField('name', array(
            'choices' => new \ArrayObject(),
        ));
    }

    /**
     * @expectedException Symfony\Component\Form\Exception\InvalidOptionsException
     */
    public function testConfigurePreferredChoicesWithNonArray()
    {
        $field = new ChoiceField('name', array(
            'choices' => $this->choices,
            'preferred_choices' => new \ArrayObject(),
        ));
    }

    public function testBindSingleNonExpanded()
    {
        $field = new ChoiceField('name', array(
            'multiple' => false,
            'expanded' => false,
            'choices' => $this->choices,
        ));

        $field->bind('b');

        $this->assertEquals('b', $field->getData());
        $this->assertEquals('b', $field->getDisplayedData());
    }

    public function testBindMultipleNonExpanded()
    {
        $field = new ChoiceField('name', array(
            'multiple' => true,
            'expanded' => false,
            'choices' => $this->choices,
        ));

        $field->bind(array('a', 'b'));

        $this->assertEquals(array('a', 'b'), $field->getData());
        $this->assertEquals(array('a', 'b'), $field->getDisplayedData());
    }

    public function testBindSingleExpanded()
    {
        $field = new ChoiceField('name', array(
            'multiple' => false,
            'expanded' => true,
            'choices' => $this->choices,
        ));

        $field->bind('b');

        $this->assertSame('b', $field->getData());
        $this->assertSame(false, $field['a']->getData());
        $this->assertSame(true, $field['b']->getData());
        $this->assertSame(false, $field['c']->getData());
        $this->assertSame(false, $field['d']->getData());
        $this->assertSame(false, $field['e']->getData());
        $this->assertSame('', $field['a']->getDisplayedData());
        $this->assertSame('1', $field['b']->getDisplayedData());
        $this->assertSame('', $field['c']->getDisplayedData());
        $this->assertSame('', $field['d']->getDisplayedData());
        $this->assertSame('', $field['e']->getDisplayedData());
        $this->assertSame(array('a' => '', 'b' => '1', 'c' => '', 'd' => '', 'e' => ''), $field->getDisplayedData());
    }

    public function testBindSingleExpandedNumericChoices()
    {
        $field = new ChoiceField('name', array(
            'multiple' => false,
            'expanded' => true,
            'choices' => $this->numericChoices,
        ));

        $field->bind('1');

        $this->assertSame(1, $field->getData());
        $this->assertSame(false, $field[0]->getData());
        $this->assertSame(true, $field[1]->getData());
        $this->assertSame(false, $field[2]->getData());
        $this->assertSame(false, $field[3]->getData());
        $this->assertSame(false, $field[4]->getData());
        $this->assertSame('', $field[0]->getDisplayedData());
        $this->assertSame('1', $field[1]->getDisplayedData());
        $this->assertSame('', $field[2]->getDisplayedData());
        $this->assertSame('', $field[3]->getDisplayedData());
        $this->assertSame('', $field[4]->getDisplayedData());
        $this->assertSame(array(0 => '', 1 => '1', 2 => '', 3 => '', 4 => ''), $field->getDisplayedData());
    }

    public function testBindMultipleExpanded()
    {
        $field = new ChoiceField('name', array(
            'multiple' => true,
            'expanded' => true,
            'choices' => $this->choices,
        ));

        $field->bind(array('a' => 'a', 'b' => 'b'));

        $this->assertSame(array('a', 'b'), $field->getData());
        $this->assertSame(true, $field['a']->getData());
        $this->assertSame(true, $field['b']->getData());
        $this->assertSame(false, $field['c']->getData());
        $this->assertSame(false, $field['d']->getData());
        $this->assertSame(false, $field['e']->getData());
        $this->assertSame('1', $field['a']->getDisplayedData());
        $this->assertSame('1', $field['b']->getDisplayedData());
        $this->assertSame('', $field['c']->getDisplayedData());
        $this->assertSame('', $field['d']->getDisplayedData());
        $this->assertSame('', $field['e']->getDisplayedData());
        $this->assertSame(array('a' => '1', 'b' => '1', 'c' => '', 'd' => '', 'e' => ''), $field->getDisplayedData());
    }

    public function testBindMultipleExpandedNumericChoices()
    {
        $field = new ChoiceField('name', array(
            'multiple' => true,
            'expanded' => true,
            'choices' => $this->numericChoices,
        ));

        $field->bind(array(1 => 1, 2 => 2));

        $this->assertSame(array(1, 2), $field->getData());
        $this->assertSame(false, $field[0]->getData());
        $this->assertSame(true, $field[1]->getData());
        $this->assertSame(true, $field[2]->getData());
        $this->assertSame(false, $field[3]->getData());
        $this->assertSame(false, $field[4]->getData());
        $this->assertSame('', $field[0]->getDisplayedData());
        $this->assertSame('1', $field[1]->getDisplayedData());
        $this->assertSame('1', $field[2]->getDisplayedData());
        $this->assertSame('', $field[3]->getDisplayedData());
        $this->assertSame('', $field[4]->getDisplayedData());
        $this->assertSame(array(0 => '', 1 => '1', 2 => '1', 3 => '', 4 => ''), $field->getDisplayedData());
    }
}