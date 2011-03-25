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

use Symfony\Component\Form\PasswordField;

class PasswordTypeTest extends TestCase
{
    public function testGetDisplayedData_beforeSubmit()
    {
        $form = $this->factory->create('password', 'name');
        $form->setData('before');

        $this->assertSame('', $form->getRenderer()->getVar('value'));
    }

    public function testGetDisplayedData_afterSubmit()
    {
        $form = $this->factory->create('password', 'name');
        $form->bind('after');

        $this->assertSame('', $form->getRenderer()->getVar('value'));
    }

    public function testGetDisplayedDataWithAlwaysEmptyDisabled_beforeSubmit()
    {
        $form = $this->factory->create('password', 'name', array('always_empty' => false));
        $form->setData('before');

        $this->assertSame('', $form->getRenderer()->getVar('value'));
    }

    public function testGetDisplayedDataWithAlwaysEmptyDisabled_afterSubmit()
    {
        $form = $this->factory->create('password', 'name', array('always_empty' => false));
        $form->bind('after');

        $this->assertSame('after', $form->getRenderer()->getVar('value'));
    }
}