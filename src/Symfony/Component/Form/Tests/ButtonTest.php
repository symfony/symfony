<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Form\Tests;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Form\ButtonBuilder;
use Symfony\Component\Form\FormBuilder;

/**
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class ButtonTest extends TestCase
{
    private $dispatcher;

    private $factory;

    protected function setUp()
    {
        $this->dispatcher = $this->getMockBuilder('Symfony\Component\EventDispatcher\EventDispatcherInterface')->getMock();
        $this->factory = $this->getMockBuilder('Symfony\Component\Form\FormFactoryInterface')->getMock();
    }

    public function testSetParentOnSubmittedButton()
    {
        $this->expectException('Symfony\Component\Form\Exception\AlreadySubmittedException');
        $button = $this->getButtonBuilder('button')
            ->getForm()
        ;

        $button->submit('');

        $button->setParent($this->getFormBuilder()->getForm());
    }

    /**
     * @dataProvider getDisabledStates
     */
    public function testDisabledIfParentIsDisabled($parentDisabled, $buttonDisabled, $result)
    {
        $form = $this->getFormBuilder()
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
        return [
            // parent, button, result
            [true, true, true],
            [true, false, true],
            [false, true, true],
            [false, false, false],
        ];
    }

    private function getButtonBuilder($name)
    {
        return new ButtonBuilder($name);
    }

    private function getFormBuilder()
    {
        return new FormBuilder('form', null, $this->dispatcher, $this->factory);
    }
}
