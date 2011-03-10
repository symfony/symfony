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

use Symfony\Component\Form\PasswordField;

class PasswordFieldTest extends \PHPUnit_Framework_TestCase
{
    public function testGetDisplayedData()
    {
        $field = new PasswordField('name');
        $field->setData('before');

        $this->assertSame('', $field->getDisplayedData());

        $field->submit('after');

        $this->assertSame('', $field->getDisplayedData());
    }

    public function testGetDisplayedDataWithAlwaysEmptyDisabled()
    {
        $field = new PasswordField('name', array('always_empty' => false));
        $field->setData('before');

        $this->assertSame('', $field->getDisplayedData());

        $field->submit('after');

        $this->assertSame('after', $field->getDisplayedData());
    }
}