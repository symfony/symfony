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
    public function testPassValueToRenderer()
    {
        $form = $this->factory->create('radio', 'name', array('value' => 'foobar'));
        $renderer = $this->factory->createRenderer($form, 'stub');

        $this->assertEquals('foobar', $renderer->getVar('value'));
    }

    public function testPassParentNameToRenderer()
    {
        $parent = $this->factory->create('field', 'parent');
        $parent->add($this->factory->create('radio', 'child'));
        $renderer = $this->factory->createRenderer($parent, 'stub');

        $this->assertEquals('parent', $renderer['child']->getVar('name'));
    }

    public function testCheckedIfDataTrue()
    {
        $form = $this->factory->create('radio');
        $form->setData(true);
        $renderer = $this->factory->createRenderer($form, 'stub');

        $this->assertTrue($renderer->getVar('checked'));
    }

    public function testNotCheckedIfDataFalse()
    {
        $form = $this->factory->create('radio');
        $form->setData(false);
        $renderer = $this->factory->createRenderer($form, 'stub');

        $this->assertFalse($renderer->getVar('checked'));
    }
}
