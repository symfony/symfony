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
    public function testPassValueToContext()
    {
        $form = $this->factory->create('checkbox', 'name', array('value' => 'foobar'));
        $context = $form->getContext();

        $this->assertEquals('foobar', $context->getVar('value'));
    }

    public function testCheckedIfDataTrue()
    {
        $form = $this->factory->create('checkbox');
        $form->setData(true);
        $context = $form->getContext();

        $this->assertTrue($context->getVar('checked'));
    }

    public function testNotCheckedIfDataFalse()
    {
        $form = $this->factory->create('checkbox');
        $form->setData(false);
        $context = $form->getContext();

        $this->assertFalse($context->getVar('checked'));
    }
}
