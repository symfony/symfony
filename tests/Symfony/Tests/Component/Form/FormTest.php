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
        $this->builder = new FormBuilder($this->dispatcher);
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
}