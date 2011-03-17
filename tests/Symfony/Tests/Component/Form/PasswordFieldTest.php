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

require_once __DIR__.'/TestCase.php';

use Symfony\Component\Form\PasswordField;

class PasswordFieldTest extends TestCase
{
    public function testGetDisplayedData_beforeSubmit()
    {
        $field = $this->factory->getInstance('password', 'name');
        $field->setData('before');

        $this->assertSame('', $field->getRenderer()->getVar('value'));
    }

    public function testGetDisplayedData_afterSubmit()
    {
        $field = $this->factory->getInstance('password', 'name');
        $field->bind('after');

        $this->assertSame('', $field->getRenderer()->getVar('value'));
    }

    public function testGetDisplayedDataWithAlwaysEmptyDisabled_beforeSubmit()
    {
        $field = $this->factory->getInstance('password', 'name', array('always_empty' => false));
        $field->setData('before');

        $this->assertSame('', $field->getRenderer()->getVar('value'));
    }

    public function testGetDisplayedDataWithAlwaysEmptyDisabled_afterSubmit()
    {
        $field = $this->factory->getInstance('password', 'name', array('always_empty' => false));
        $field->bind('after');

        $this->assertSame('after', $field->getRenderer()->getVar('value'));
    }
}