<?php

/*
 * This file is part of the Symfony package.
 *
 * Author: Bhavin Nakrani
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

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
        $this->assertFalse($this->formView->hasParent());
    }
}
