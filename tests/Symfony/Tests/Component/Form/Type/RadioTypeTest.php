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

class RadioTypeTest extends TestCase
{
    public function testPassValueToView()
    {
        $form = $this->factory->create('radio', 'name', array('value' => 'foobar'));
        $view = $form->createView();

        $this->assertEquals('foobar', $view->getVar('value'));
    }

    public function testPassParentNameToView()
    {
        $parent = $this->factory->create('field', 'parent');
        $parent->add($this->factory->create('radio', 'child'));
        $view = $parent->createView();

        $this->assertEquals('parent', $view['child']->getVar('name'));
    }

    public function testCheckedIfDataTrue()
    {
        $form = $this->factory->create('radio');
        $form->setData(true);
        $view = $form->createView();

        $this->assertTrue($view->getVar('checked'));
    }

    public function testNotCheckedIfDataFalse()
    {
        $form = $this->factory->create('radio');
        $form->setData(false);
        $view = $form->createView();

        $this->assertFalse($view->getVar('checked'));
    }
}
