<?php

namespace Symfony\Component\Form\Tests;


use Symfony\Component\Form\FormView;

class FormViewTest extends \PHPUnit_Framework_TestCase
{
    private $formView;

    protected function setUp()
    {
        $this->formView = $this->getMock('Symfony\Component\Form\FormView');
    }

    public function testHasParent()
    {
        $this->formView->parent = new FormView();
        $this->assertTrue($this->formView->hasParent());
    }

}