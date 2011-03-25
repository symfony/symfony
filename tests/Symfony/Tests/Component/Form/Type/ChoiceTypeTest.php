<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Tests\Component\Form\Type;

require_once __DIR__.'/TestCase.php';

use Symfony\Component\Form\ChoiceField;
use Symfony\Component\Form\Exception\UnexpectedTypeException;

class ChoiceTypeTest extends TestCase
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
        $form = $this->factory->create('choice', 'name', array(
            'choices' => array(
                0 => 'Foo',
                '' => 'Bar',
            )
        ));

        $form->bind(0);

        $this->assertTrue($form->getRenderer()->getVar('choice_list')->isChoiceSelected(0, $form->getClientData()));
        $this->assertTrue($form->getRenderer()->getVar('choice_list')->isChoiceSelected('0', $form->getClientData()));
        $this->assertFalse($form->getRenderer()->getVar('choice_list')->isChoiceSelected('', $form->getClientData()));

        $form->bind('0');

        $this->assertTrue($form->getRenderer()->getVar('choice_list')->isChoiceSelected(0, $form->getClientData()));
        $this->assertTrue($form->getRenderer()->getVar('choice_list')->isChoiceSelected('0', $form->getClientData()));
        $this->assertFalse($form->getRenderer()->getVar('choice_list')->isChoiceSelected('', $form->getClientData()));

        $form->bind('');

        $this->assertFalse($form->getRenderer()->getVar('choice_list')->isChoiceSelected(0, $form->getClientData()));
        $this->assertFalse($form->getRenderer()->getVar('choice_list')->isChoiceSelected('0', $form->getClientData()));
        $this->assertTrue($form->getRenderer()->getVar('choice_list')->isChoiceSelected('', $form->getClientData()));
    }

    public function testIsChoiceSelectedDifferentiatesBetweenZeroAndEmpty_stringZero()
    {
        $form = $this->factory->create('choice', 'name', array(
            'choices' => array(
                '0' => 'Foo',
                '' => 'Bar',
            )
        ));

        $form->bind(0);

        $this->assertTrue($form->getRenderer()->getVar('choice_list')->isChoiceSelected(0, $form->getClientData()));
        $this->assertTrue($form->getRenderer()->getVar('choice_list')->isChoiceSelected('0', $form->getClientData()));
        $this->assertFalse($form->getRenderer()->getVar('choice_list')->isChoiceSelected('', $form->getClientData()));

        $form->bind('0');

        $this->assertTrue($form->getRenderer()->getVar('choice_list')->isChoiceSelected(0, $form->getClientData()));
        $this->assertTrue($form->getRenderer()->getVar('choice_list')->isChoiceSelected('0', $form->getClientData()));
        $this->assertFalse($form->getRenderer()->getVar('choice_list')->isChoiceSelected('', $form->getClientData()));

        $form->bind('');

        $this->assertFalse($form->getRenderer()->getVar('choice_list')->isChoiceSelected(0, $form->getClientData()));
        $this->assertFalse($form->getRenderer()->getVar('choice_list')->isChoiceSelected('0', $form->getClientData()));
        $this->assertTrue($form->getRenderer()->getVar('choice_list')->isChoiceSelected('', $form->getClientData()));
    }

    /**
     * @expectedException Symfony\Component\Form\Exception\UnexpectedTypeException
     */
    public function testConfigureChoicesWithNonArray()
    {
        $form = $this->factory->create('choice', 'name', array(
            'choices' => new \ArrayObject(),
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
     * @expectedException Symfony\Component\Form\Exception\UnexpectedTypeException
     */
    public function testClosureShouldReturnArray()
    {
        $form = $this->factory->create('choice', 'name', array(
            'choices' => function () { return 'foobar'; },
        ));

        // trigger closure
        $form->getRenderer()->getVar('choices');
    }

    /**
     * @dataProvider getChoicesVariants
     */
    public function testSubmitSingleNonExpanded($choices)
    {
        $form = $this->factory->create('choice', 'name', array(
            'multiple' => false,
            'expanded' => false,
            'choices' => $choices,
        ));

        $form->bind('b');

        $this->assertEquals('b', $form->getData());
        $this->assertEquals('b', $form->getClientData());
    }

    /**
     * @dataProvider getChoicesVariants
     */
    public function testSubmitMultipleNonExpanded($choices)
    {
        $form = $this->factory->create('choice', 'name', array(
            'multiple' => true,
            'expanded' => false,
            'choices' => $choices,
        ));

        $form->bind(array('a', 'b'));

        $this->assertEquals(array('a', 'b'), $form->getData());
        $this->assertEquals(array('a', 'b'), $form->getClientData());
    }

    /**
     * @dataProvider getChoicesVariants
     */
    public function testSubmitSingleExpanded($choices)
    {
        $form = $this->factory->create('choice', 'name', array(
            'multiple' => false,
            'expanded' => true,
            'choices' => $choices,
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

    /**
     * @dataProvider getNumericChoicesVariants
     */
    public function testSubmitSingleExpandedNumericChoices($choices)
    {
        $form = $this->factory->create('choice', 'name', array(
            'multiple' => false,
            'expanded' => true,
            'choices' => $choices,
        ));

        $form->bind('1');

        $this->assertSame(1, $form->getData());
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

    /**
     * @dataProvider getChoicesVariants
     */
    public function testSubmitMultipleExpanded($choices)
    {
        $form = $this->factory->create('choice', 'name', array(
            'multiple' => true,
            'expanded' => true,
            'choices' => $choices,
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

    /**
     * @dataProvider getNumericChoicesVariants
     */
    public function testSubmitMultipleExpandedNumericChoices($choices)
    {
        $form = $this->factory->create('choice', 'name', array(
            'multiple' => true,
            'expanded' => true,
            'choices' => $choices,
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
}