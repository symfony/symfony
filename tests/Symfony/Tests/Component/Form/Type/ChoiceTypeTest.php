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
        $form = $this->factory->create('choice', 'name', array(
            'choices' => new \ArrayObject(),
        ));
    }

    public function testBindSingleNonExpanded()
    {
        $form = $this->factory->create('choice', 'name', array(
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
        $form = $this->factory->create('choice', 'name', array(
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
        $form = $this->factory->create('choice', 'name', array(
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

    public function testBindSingleExpandedNumericChoices()
    {
        $form = $this->factory->create('choice', 'name', array(
            'multiple' => false,
            'expanded' => true,
            'choices' => $this->numericChoices,
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

    public function testBindMultipleExpanded()
    {
        $form = $this->factory->create('choice', 'name', array(
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
        $form = $this->factory->create('choice', 'name', array(
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
     * We need this functionality to create choice fields for boolean types,
     * e.g. false => 'No', true => 'Yes'
     */
    public function testSetDataSingleNonExpandedAcceptsBoolean()
    {
        $form = $this->factory->create('choice', 'name', array(
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
        $form = $this->factory->create('choice', 'name', array(
            'multiple' => true,
            'expanded' => false,
            'choices' => $this->numericChoices,
        ));

        $form->setData(array(false, true));

        $this->assertEquals(array(false, true), $form->getData());
        $this->assertEquals(array('0', '1'), $form->getClientData());
    }

    /**
     * @expectedException Symfony\Component\Form\Exception\FormException
     */
    public function testRequiresChoicesOrChoiceListOption()
    {
        $this->factory->create('choice', 'name');
    }

    public function testPassMultipleToContext()
    {
        $form = $this->factory->create('choice', 'name', array(
            'multiple' => true,
            'choices' => $this->choices,
        ));
        $context = $form->getContext();

        $this->assertTrue($context->getVar('multiple'));
    }

    public function testPassExpandedToContext()
    {
        $form = $this->factory->create('choice', 'name', array(
            'expanded' => true,
            'choices' => $this->choices,
        ));
        $context = $form->getContext();

        $this->assertTrue($context->getVar('expanded'));
    }

    public function testPassChoicesToContext()
    {
        $choices = array('a' => 'A', 'b' => 'B', 'c' => 'C', 'd' => 'D');
        $form = $this->factory->create('choice', 'name', array(
            'choices' => $choices,
        ));
        $context = $form->getContext();

        $this->assertSame($choices, $context->getVar('choices'));
    }

    public function testPassPreferredChoicesToContext()
    {
        $choices = array('a' => 'A', 'b' => 'B', 'c' => 'C', 'd' => 'D');
        $form = $this->factory->create('choice', 'name', array(
            'choices' => $choices,
            'preferred_choices' => array('b', 'd'),
        ));
        $context = $form->getContext();

        $this->assertSame(array('a' => 'A', 'c' => 'C'), $context->getVar('choices'));
        $this->assertSame(array('b' => 'B', 'd' => 'D'), $context->getVar('preferred_choices'));
    }

    public function testAdjustNameForMultipleNonExpanded()
    {
        $form = $this->factory->create('choice', 'name', array(
            'multiple' => true,
            'expanded' => false,
            'choices' => $this->choices,
        ));
        $context = $form->getContext();

        $this->assertSame('name[]', $context->getVar('name'));
    }
}
