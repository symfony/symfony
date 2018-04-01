<?php

/*
 * This file is part of the Symphony package.
 *
 * (c) Fabien Potencier <fabien@symphony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symphony\Component\Form\Tests;

use PHPUnit\Framework\TestCase;
use Symphony\Component\Form\ButtonBuilder;
use Symphony\Component\Form\FormBuilder;

/**
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class ButtonTest extends TestCase
{
    private $dispatcher;

    private $factory;

    protected function setUp()
    {
        $this->dispatcher = $this->getMockBuilder('Symphony\Component\EventDispatcher\EventDispatcherInterface')->getMock();
        $this->factory = $this->getMockBuilder('Symphony\Component\Form\FormFactoryInterface')->getMock();
    }

    /**
     * @expectedException \Symphony\Component\Form\Exception\AlreadySubmittedException
     */
    public function testSetParentOnSubmittedButton()
    {
        $button = $this->getButtonBuilder('button')
            ->getForm()
        ;

        $button->submit('');

        $button->setParent($this->getFormBuilder('form')->getForm());
    }

    /**
     * @dataProvider getDisabledStates
     */
    public function testDisabledIfParentIsDisabled($parentDisabled, $buttonDisabled, $result)
    {
        $form = $this->getFormBuilder('form')
            ->setDisabled($parentDisabled)
            ->getForm()
        ;

        $button = $this->getButtonBuilder('button')
            ->setDisabled($buttonDisabled)
            ->getForm()
        ;

        $button->setParent($form);

        $this->assertSame($result, $button->isDisabled());
    }

    public function getDisabledStates()
    {
        return array(
            // parent, button, result
            array(true, true, true),
            array(true, false, true),
            array(false, true, true),
            array(false, false, false),
        );
    }

    private function getButtonBuilder($name)
    {
        return new ButtonBuilder($name);
    }

    private function getFormBuilder($name)
    {
        return new FormBuilder($name, null, $this->dispatcher, $this->factory);
    }
}
