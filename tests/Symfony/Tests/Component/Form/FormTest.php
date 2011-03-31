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

use Symfony\Component\Form\Form;
use Symfony\Component\Form\FormBuilder;
use Symfony\Component\Form\FormError;

class FormTest extends \PHPUnit_Framework_TestCase
{
    private $dispatcher;

    private $builder;

    private $form;

    protected function setUp()
    {
        $this->dispatcher = $this->getMock('Symfony\Component\EventDispatcher\EventDispatcherInterface');
        $this->builder = new FormBuilder('name', $this->dispatcher);
        $this->form = $this->builder->getForm();
    }

    public function testErrorsBubbleUpIfEnabled()
    {
        $error = new FormError('Error!');
        $parent = $this->form;
        $form = $this->builder->setErrorBubbling(true)->getForm();

        $form->setParent($parent);
        $form->addError($error);

        $this->assertEquals(array(), $form->getErrors());
        $this->assertEquals(array($error), $parent->getErrors());
    }

    public function testErrorsDontBubbleUpIfDisabled()
    {
        $error = new FormError('Error!');
        $parent = $this->form;
        $form = $this->builder->setErrorBubbling(false)->getForm();

        $form->setParent($parent);
        $form->addError($error);

        $this->assertEquals(array($error), $form->getErrors());
        $this->assertEquals(array(), $parent->getErrors());
    }

    public function testValidIfAllChildrenAreValid()
    {
        $this->form->add($this->getValidForm('firstName'));
        $this->form->add($this->getValidForm('lastName'));

        $this->form->bind(array(
            'firstName' => 'Bernhard',
            'lastName' => 'Schussek',
        ));

        $this->assertTrue($this->form->isValid());
    }

    public function testInvalidIfChildrenIsInvalid()
    {
        $this->form->add($this->getValidForm('firstName'));
        $this->form->add($this->getInvalidForm('lastName'));

        $this->form->bind(array(
            'firstName' => 'Bernhard',
            'lastName' => 'Schussek',
        ));

        $this->assertFalse($this->form->isValid());
    }

    public function testBind()
    {
        $child = $this->getMockForm('firstName');

        $this->form->add($child);

        $child->expects($this->once())
            ->method('bind')
            ->with($this->equalTo('Bernhard'));

        $this->form->bind(array('firstName' => 'Bernhard'));

        $this->assertEquals(array('firstName' => 'Bernhard'), $this->form->getData());
    }

    public function testBindForwardsNullIfValueIsMissing()
    {
        $child = $this->getMockForm('firstName');

        $this->form->add($child);

        $child->expects($this->once())
            ->method('bind')
            ->with($this->equalTo(null));

        $this->form->bind(array());
    }

    public function testAddSetsFieldParent()
    {
        $child = $this->getMockForm('firstName');

        $child->expects($this->once())
            ->method('setParent')
            ->with($this->equalTo($this->form));

        $this->form->add($child);
    }

    protected function getMockForm($name)
    {
        $form = $this->getMock('Symfony\Tests\Component\Form\FormInterface');

        $form->expects($this->any())
            ->method('getName')
            ->will($this->returnValue($name));

        return $form;
    }

    protected function getValidForm($name)
    {
        $form = $this->getMockForm($name);

        $form->expects($this->any())
            ->method('isValid')
            ->will($this->returnValue(true));

        return $form;
    }

    protected function getInvalidForm($name)
    {
        $form = $this->getMockForm($name);

        $form->expects($this->any())
            ->method('isValid')
            ->will($this->returnValue(false));

        return $form;
    }
}