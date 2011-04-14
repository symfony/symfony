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

class CheckboxTypeTest extends TestCase
{
    public function testPassValueToView()
    {
        $form = $this->factory->create('checkbox', 'name', array('value' => 'foobar'));
        $view = $form->getView();

        $this->assertEquals('foobar', $view->getVar('value'));
    }

    public function testCheckedIfDataTrue()
    {
        $form = $this->factory->create('checkbox');
        $form->setData(true);
        $view = $form->getView();

        $this->assertTrue($view->getVar('checked'));
    }

    public function testNotCheckedIfDataFalse()
    {
        $form = $this->factory->create('checkbox');
        $form->setData(false);
        $view = $form->getView();

        $this->assertFalse($view->getVar('checked'));
    }
}
