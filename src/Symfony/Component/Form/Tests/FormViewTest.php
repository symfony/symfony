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

use Symfony\Component\Form\FormView;

/**
 * @author Jonatan Männchen <jonatan@maennchen.ch>
 */
class FormViewTest extends \PHPUnit_Framework_TestCase
{
    public function testHasFormView()
    {
        $formView = new FormView($this->getMock(FormView::class));
        $this->assertTrue($formView->hasParent());
    }

    public function testNotHasFormView()
    {
        $formView = new FormView();
        $this->assertFalse($formView->hasParent());
    }
}
