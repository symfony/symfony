<?php

namespace Symfony\Tests\Component\Form;

/*
 * This file is part of the Symfony framework.
 *
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

use Symfony\Component\Form\PasswordField;

class PasswordFieldTest extends \PHPUnit_Framework_TestCase
{
    public function testGetDisplayedData()
    {
        $field = new PasswordField('name');
        $field->setData('before');

        $this->assertSame('', $field->getDisplayedData());

        $field->bind('after');

        $this->assertSame('', $field->getDisplayedData());
    }

    public function testGetDisplayedDataWithAlwaysEmptyDisabled()
    {
        $field = new PasswordField('name', array('always_empty' => false));
        $field->setData('before');

        $this->assertSame('', $field->getDisplayedData());

        $field->bind('after');

        $this->assertSame('after', $field->getDisplayedData());
    }
}