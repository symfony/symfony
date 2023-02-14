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
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\Form\ButtonBuilder;
use Symfony\Component\Form\Exception\AlreadySubmittedException;
use Symfony\Component\Form\FormBuilder;
use Symfony\Component\Form\FormFactory;
use Symfony\Component\Form\FormRegistry;
use Symfony\Component\Form\ResolvedFormTypeFactory;

/**
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class ButtonTest extends TestCase
{
    public function testSetParentOnSubmittedButton()
    {
        $this->expectException(AlreadySubmittedException::class);
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

    public static function getDisabledStates()
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
        return new FormBuilder('form', null, new EventDispatcher(), new FormFactory(new FormRegistry([], new ResolvedFormTypeFactory())));
    }
}
