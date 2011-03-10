<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

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

    public function testIsChoiceSelectedDifferentiatesBetweenZeroAndEmpty_integerZero()
    {
        $field = new ChoiceField('name', array(
            'choices' => array(
                0 => 'Foo',
                '' => 'Bar',
            )
        ));

        $field->submit(0);

        $this->assertTrue($field->isChoiceSelected(0));
        $this->assertTrue($field->isChoiceSelected('0'));
        $this->assertFalse($field->isChoiceSelected(''));

        $field->submit('0');

        $this->assertTrue($field->isChoiceSelected(0));
        $this->assertTrue($field->isChoiceSelected('0'));
        $this->assertFalse($field->isChoiceSelected(''));

        $field->submit('');

        $this->assertFalse($field->isChoiceSelected(0));
        $this->assertFalse($field->isChoiceSelected('0'));
        $this->assertTrue($field->isChoiceSelected(''));
    }

    public function testIsChoiceSelectedDifferentiatesBetweenZeroAndEmpty_stringZero()
    {
        $field = new ChoiceField('name', array(
            'choices' => array(
                '0' => 'Foo',
                '' => 'Bar',
            )
        ));

        $field->submit(0);

        $this->assertTrue($field->isChoiceSelected(0));
        $this->assertTrue($field->isChoiceSelected('0'));
        $this->assertFalse($field->isChoiceSelected(''));

        $field->submit('0');

        $this->assertTrue($field->isChoiceSelected(0));
        $this->assertTrue($field->isChoiceSelected('0'));
        $this->assertFalse($field->isChoiceSelected(''));

        $field->submit('');

        $this->assertFalse($field->isChoiceSelected(0));
        $this->assertFalse($field->isChoiceSelected('0'));
        $this->assertTrue($field->isChoiceSelected(''));
    }

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

    public function getChoicesVariants()
    {
        $choices = $this->choices;

        return array(
            array($choices),
            array(function () use ($choices) { return $choices; }),
        );
    }

    public function getNumericChoicesVariants()
    {
        $choices = $this->numericChoices;

        return array(
            array($choices),
            array(function () use ($choices) { return $choices; }),
        );
    }

    /**
     * @expectedException Symfony\Component\Form\Exception\InvalidOptionsException
     */
    public function testClosureShouldReturnArray()
    {
        $field = new ChoiceField('name', array(
            'choices' => function () { return 'foobar'; },
        ));

        // trigger closure
        $field->getOtherChoices();
    }

    public function testNonRequiredContainsEmptyField()
    {
        $field = new ChoiceField('name', array(
            'multiple' => false,
            'expanded' => false,
            'choices' => $this->choices,
            'required' => false,
        ));

        $this->assertEquals(array('' => '') + $this->choices, $field->getOtherChoices());
    }

    public function testRequiredContainsNoEmptyField()
    {
        $field = new ChoiceField('name', array(
            'multiple' => false,
            'expanded' => false,
            'choices' => $this->choices,
            'required' => true,
        ));

        $this->assertEquals($this->choices, $field->getOtherChoices());
    }

    public function testEmptyValueConfiguresLabelOfEmptyField()
    {
        $field = new ChoiceField('name', array(
            'multiple' => false,
            'expanded' => false,
            'choices' => $this->choices,
            'required' => false,
            'empty_value' => 'Foobar',
        ));

        $this->assertEquals(array('' => 'Foobar') + $this->choices, $field->getOtherChoices());
    }

    /**
     * @dataProvider getChoicesVariants
     */
    public function testSubmitSingleNonExpanded($choices)
    {
        $field = new ChoiceField('name', array(
            'multiple' => false,
            'expanded' => false,
            'choices' => $choices,
        ));

        $field->submit('b');

        $this->assertEquals('b', $field->getData());
        $this->assertEquals('b', $field->getDisplayedData());
    }

    /**
     * @dataProvider getChoicesVariants
     */
    public function testSubmitMultipleNonExpanded($choices)
    {
        $field = new ChoiceField('name', array(
            'multiple' => true,
            'expanded' => false,
            'choices' => $choices,
        ));

        $field->submit(array('a', 'b'));

        $this->assertEquals(array('a', 'b'), $field->getData());
        $this->assertEquals(array('a', 'b'), $field->getDisplayedData());
    }

    /**
     * @dataProvider getChoicesVariants
     */
    public function testSubmitSingleExpanded($choices)
    {
        $field = new ChoiceField('name', array(
            'multiple' => false,
            'expanded' => true,
            'choices' => $choices,
        ));

        $field->submit('b');

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

    /**
     * @dataProvider getNumericChoicesVariants
     */
    public function testSubmitSingleExpandedNumericChoices($choices)
    {
        $field = new ChoiceField('name', array(
            'multiple' => false,
            'expanded' => true,
            'choices' => $choices,
        ));

        $field->submit('1');

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

    /**
     * @dataProvider getChoicesVariants
     */
    public function testSubmitMultipleExpanded($choices)
    {
        $field = new ChoiceField('name', array(
            'multiple' => true,
            'expanded' => true,
            'choices' => $choices,
        ));

        $field->submit(array('a' => 'a', 'b' => 'b'));

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

    /**
     * @dataProvider getNumericChoicesVariants
     */
    public function testSubmitMultipleExpandedNumericChoices($choices)
    {
        $field = new ChoiceField('name', array(
            'multiple' => true,
            'expanded' => true,
            'choices' => $choices,
        ));

        $field->submit(array(1 => 1, 2 => 2));

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