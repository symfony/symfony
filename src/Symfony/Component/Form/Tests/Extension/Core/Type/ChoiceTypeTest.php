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

use Symfony\Component\Form\Extension\Core\ChoiceList\ObjectChoiceList;
use Symfony\Component\Form\Extension\Core\View\ChoiceView;

class ChoiceTypeTest extends \Symfony\Component\Form\Test\TypeTestCase
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
     * @expectedException \PHPUnit_Framework_Error
     */
    public function testChoicesOptionExpectsArray()
    {
        $this->factory->create('choice', null, array(
            'choices' => new \ArrayObject(),
        ));
    }

    /**
     * @expectedException \Symfony\Component\OptionsResolver\Exception\InvalidOptionsException
     */
    public function testChoiceListOptionExpectsChoiceListInterface()
    {
        $this->factory->create('choice', null, array(
            'choice_list' => array('foo' => 'foo'),
        ));
    }

    public function testChoiceListAndChoicesCanBeEmpty()
    {
        $this->factory->create('choice');
    }

    public function testExpandedChoicesOptionsTurnIntoChildren()
    {
        $form = $this->factory->create('choice', null, array(
            'expanded' => true,
            'choices' => $this->choices,
        ));

        $this->assertCount(count($this->choices), $form, 'Each choice should become a new field');
    }

    public function testPlaceholderPresentOnNonRequiredExpandedSingleChoice()
    {
        $form = $this->factory->create('choice', null, array(
            'multiple' => false,
            'expanded' => true,
            'required' => false,
            'choices' => $this->choices,
        ));

        $this->assertTrue(isset($form['placeholder']));
        $this->assertCount(count($this->choices) + 1, $form, 'Each choice should become a new field');
    }

    public function testPlaceholderNotPresentIfRequired()
    {
        $form = $this->factory->create('choice', null, array(
            'multiple' => false,
            'expanded' => true,
            'required' => true,
            'choices' => $this->choices,
        ));

        $this->assertFalse(isset($form['placeholder']));
        $this->assertCount(count($this->choices), $form, 'Each choice should become a new field');
    }

    public function testPlaceholderNotPresentIfMultiple()
    {
        $form = $this->factory->create('choice', null, array(
            'multiple' => true,
            'expanded' => true,
            'required' => false,
            'choices' => $this->choices,
        ));

        $this->assertFalse(isset($form['placeholder']));
        $this->assertCount(count($this->choices), $form, 'Each choice should become a new field');
    }

    public function testPlaceholderNotPresentIfEmptyChoice()
    {
        $form = $this->factory->create('choice', null, array(
            'multiple' => false,
            'expanded' => true,
            'required' => false,
            'choices' => array(
                '' => 'Empty',
                1 => 'Not empty',
            ),
        ));

        $this->assertFalse(isset($form['placeholder']));
        $this->assertCount(2, $form, 'Each choice should become a new field');
    }

    public function testExpandedChoicesOptionsAreFlattened()
    {
        $form = $this->factory->create('choice', null, array(
            'expanded' => true,
            'choices' => $this->groupedChoices,
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

    public function testSubmitSingleNonExpanded()
    {
        $form = $this->factory->create('choice', null, array(
            'multiple' => false,
            'expanded' => false,
            'choices' => $this->choices,
        ));

        $form->submit('b');

        $this->assertEquals('b', $form->getData());
        $this->assertEquals('b', $form->getViewData());
    }

    public function testSubmitSingleNonExpandedInvalidChoice()
    {
        $form = $this->factory->create('choice', null, array(
            'multiple' => false,
            'expanded' => false,
            'choices' => $this->choices,
        ));

        $form->submit('foobar');

        $this->assertNull($form->getData());
        $this->assertEquals('foobar', $form->getViewData());
        $this->assertFalse($form->isSynchronized());
    }

    public function testSubmitSingleNonExpandedObjectChoices()
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
        $form->submit('2');

        $this->assertEquals($this->objectChoices[1], $form->getData());
        $this->assertEquals('2', $form->getViewData());
    }

    public function testSubmitMultipleNonExpanded()
    {
        $form = $this->factory->create('choice', null, array(
            'multiple' => true,
            'expanded' => false,
            'choices' => $this->choices,
        ));

        $form->submit(array('a', 'b'));

        $this->assertEquals(array('a', 'b'), $form->getData());
        $this->assertEquals(array('a', 'b'), $form->getViewData());
    }

    public function testSubmitMultipleNonExpandedInvalidScalarChoice()
    {
        $form = $this->factory->create('choice', null, array(
            'multiple' => true,
            'expanded' => false,
            'choices' => $this->choices,
        ));

        $form->submit('foobar');

        $this->assertNull($form->getData());
        $this->assertEquals('foobar', $form->getViewData());
        $this->assertFalse($form->isSynchronized());
    }

    public function testSubmitMultipleNonExpandedInvalidArrayChoice()
    {
        $form = $this->factory->create('choice', null, array(
            'multiple' => true,
            'expanded' => false,
            'choices' => $this->choices,
        ));

        $form->submit(array('a', 'foobar'));

        $this->assertNull($form->getData());
        $this->assertEquals(array('a', 'foobar'), $form->getViewData());
        $this->assertFalse($form->isSynchronized());
    }

    public function testSubmitMultipleNonExpandedObjectChoices()
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

        $form->submit(array('2', '3'));

        $this->assertEquals(array($this->objectChoices[1], $this->objectChoices[2]), $form->getData());
        $this->assertEquals(array('2', '3'), $form->getViewData());
    }

    public function testSubmitSingleExpandedRequired()
    {
        $form = $this->factory->create('choice', null, array(
            'multiple' => false,
            'expanded' => true,
            'required' => true,
            'choices' => $this->choices,
        ));

        $form->submit('b');

        $this->assertSame('b', $form->getData());
        $this->assertSame(array(
            0 => false,
            1 => true,
            2 => false,
            3 => false,
            4 => false,
        ), $form->getViewData());
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
        $form = $this->factory->create('choice', null, array(
            'multiple' => false,
            'expanded' => true,
            'required' => true,
            'choices' => $this->choices,
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
        $form = $this->factory->create('choice', null, array(
            'multiple' => false,
            'expanded' => true,
            'required' => false,
            'choices' => $this->choices,
        ));

        $form->submit('b');

        $this->assertSame('b', $form->getData());
        $this->assertSame(array(
            0 => false,
            1 => true,
            2 => false,
            3 => false,
            4 => false,
            'placeholder' => false,
        ), $form->getViewData());
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
        $form = $this->factory->create('choice', null, array(
            'multiple' => false,
            'expanded' => true,
            'required' => false,
            'choices' => $this->choices,
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
        $form = $this->factory->create('choice', null, array(
            'multiple' => false,
            'expanded' => true,
            'required' => true,
            'choices' => $this->choices,
        ));

        $form->submit(null);

        $this->assertNull($form->getData());
        $this->assertSame(array(
            0 => false,
            1 => false,
            2 => false,
            3 => false,
            4 => false,
        ), $form->getViewData());
        $this->assertEmpty($form->getExtraData());
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

    public function testSubmitSingleExpandedRequiredEmpty()
    {
        $form = $this->factory->create('choice', null, array(
            'multiple' => false,
            'expanded' => true,
            'required' => true,
            'choices' => $this->choices,
        ));

        $form->submit('');

        $this->assertNull($form->getData());
        $this->assertSame(array(
            0 => false,
            1 => false,
            2 => false,
            3 => false,
            4 => false,
        ), $form->getViewData());
        $this->assertEmpty($form->getExtraData());
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

    public function testSubmitSingleExpandedRequiredFalse()
    {
        $form = $this->factory->create('choice', null, array(
            'multiple' => false,
            'expanded' => true,
            'required' => true,
            'choices' => $this->choices,
        ));

        $form->submit(false);

        $this->assertNull($form->getData());
        $this->assertSame(array(
            0 => false,
            1 => false,
            2 => false,
            3 => false,
            4 => false,
        ), $form->getViewData());
        $this->assertEmpty($form->getExtraData());
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

    public function testSubmitSingleExpandedNonRequiredNull()
    {
        $form = $this->factory->create('choice', null, array(
            'multiple' => false,
            'expanded' => true,
            'required' => false,
            'choices' => $this->choices,
        ));

        $form->submit(null);

        $this->assertNull($form->getData());
        $this->assertSame(array(
            0 => false,
            1 => false,
            2 => false,
            3 => false,
            4 => false,
            'placeholder' => true,
        ), $form->getViewData());
        $this->assertEmpty($form->getExtraData());
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

    public function testSubmitSingleExpandedNonRequiredEmpty()
    {
        $form = $this->factory->create('choice', null, array(
            'multiple' => false,
            'expanded' => true,
            'required' => false,
            'choices' => $this->choices,
        ));

        $form->submit('');

        $this->assertNull($form->getData());
        $this->assertSame(array(
            0 => false,
            1 => false,
            2 => false,
            3 => false,
            4 => false,
            'placeholder' => true,
        ), $form->getViewData());
        $this->assertEmpty($form->getExtraData());
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

    public function testSubmitSingleExpandedNonRequiredFalse()
    {
        $form = $this->factory->create('choice', null, array(
            'multiple' => false,
            'expanded' => true,
            'required' => false,
            'choices' => $this->choices,
        ));

        $form->submit(false);

        $this->assertNull($form->getData());
        $this->assertSame(array(
            0 => false,
            1 => false,
            2 => false,
            3 => false,
            4 => false,
            'placeholder' => true,
        ), $form->getViewData());
        $this->assertEmpty($form->getExtraData());
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

    public function testSubmitSingleExpandedWithEmptyChild()
    {
        $form = $this->factory->create('choice', null, array(
            'multiple' => false,
            'expanded' => true,
            'choices' => array(
                '' => 'Empty',
                1 => 'Not empty',
            ),
        ));

        $form->submit('');

        $this->assertNull($form->getData());
        $this->assertTrue($form[0]->getData());
        $this->assertFalse($form[1]->getData());
        $this->assertSame('', $form[0]->getViewData());
        $this->assertNull($form[1]->getViewData());
    }

    public function testSubmitSingleExpandedObjectChoices()
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

        $form->submit('2');

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

    public function testSubmitSingleExpandedNumericChoices()
    {
        $form = $this->factory->create('choice', null, array(
            'multiple' => false,
            'expanded' => true,
            'choices' => $this->numericChoices,
        ));

        $form->submit('1');

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

    public function testSubmitMultipleExpanded()
    {
        $form = $this->factory->create('choice', null, array(
            'multiple' => true,
            'expanded' => true,
            'choices' => $this->choices,
        ));

        $form->submit(array('a', 'c'));

        $this->assertSame(array('a', 'c'), $form->getData());
        $this->assertSame(array(
            0 => true,
            1 => false,
            2 => true,
            3 => false,
            4 => false,
        ), $form->getViewData());
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
        $form = $this->factory->create('choice', null, array(
            'multiple' => true,
            'expanded' => true,
            'choices' => $this->choices,
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
        $form = $this->factory->create('choice', null, array(
            'multiple' => true,
            'expanded' => true,
            'choices' => $this->choices,
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
        $form = $this->factory->create('choice', null, array(
            'multiple' => true,
            'expanded' => true,
            'choices' => $this->choices,
        ));

        $form->submit(array());

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

    public function testSubmitMultipleExpandedWithEmptyChild()
    {
        $form = $this->factory->create('choice', null, array(
            'multiple' => true,
            'expanded' => true,
            'choices' => array(
                '' => 'Empty',
                1 => 'Not Empty',
                2 => 'Not Empty 2',
            ),
        ));

        $form->submit(array('', '2'));

        $this->assertSame(array('', 2), $form->getData());
        $this->assertTrue($form[0]->getData());
        $this->assertFalse($form[1]->getData());
        $this->assertTrue($form[2]->getData());
        $this->assertSame('', $form[0]->getViewData());
        $this->assertNull($form[1]->getViewData());
        $this->assertSame('2', $form[2]->getViewData());
    }

    public function testSubmitMultipleExpandedObjectChoices()
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

        $form->submit(array('1', '2'));

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

    public function testSubmitMultipleExpandedNumericChoices()
    {
        $form = $this->factory->create('choice', null, array(
            'multiple' => true,
            'expanded' => true,
            'choices' => $this->numericChoices,
        ));

        $form->submit(array('1', '2'));

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

    public function testEmptyValueIsNullByDefaultIfRequired()
    {
        $form = $this->factory->create('choice', null, array(
            'multiple' => false,
            'required' => true,
            'choices' => $this->choices,
        ));
        $view = $form->createView();

        $this->assertNull($view->vars['empty_value']);
    }

    public function testEmptyValueIsEmptyStringByDefaultIfNotRequired()
    {
        $form = $this->factory->create('choice', null, array(
            'multiple' => false,
            'required' => false,
            'choices' => $this->choices,
        ));
        $view = $form->createView();

        $this->assertSame('', $view->vars['empty_value']);
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
        $this->assertFalse($view->vars['empty_value_in_choices']);
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
        $this->assertTrue($view->vars['empty_value_in_choices']);
    }

    public function getOptionsWithEmptyValue()
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
            array(false, true, true, 'foobar', 'foobar'),
            // radios should never have an empty label
            array(false, true, true, '', 'None'),
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

    // https://github.com/symfony/symfony/issues/10409
    public function testReuseNonUtf8ChoiceLists()
    {
        $form1 = $this->factory->createNamed('name', 'choice', null, array(
            'choices' => array(
                'meter' => 'm',
                'millimeter' => 'mm',
                'micrometer' => chr(181).'meter',
            ),
        ));

        $form2 = $this->factory->createNamed('name', 'choice', null, array(
            'choices' => array(
                'meter' => 'm',
                'millimeter' => 'mm',
                'micrometer' => chr(181).'meter',
            ),
        ));

        $form3 = $this->factory->createNamed('name', 'choice', null, array(
            'choices' => array(
                'meter' => 'm',
                'millimeter' => 'mm',
                'micrometer' => null,
            ),
        ));

        // $form1 and $form2 use the same ChoiceList
        $this->assertSame(
            $form1->getConfig()->getOption('choice_list'),
            $form2->getConfig()->getOption('choice_list')
        );

        // $form3 doesn't, but used to use the same when using json_encode()
        // instead of serialize for the hashing algorithm
        $this->assertNotSame(
            $form1->getConfig()->getOption('choice_list'),
            $form3->getConfig()->getOption('choice_list')
        );
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
