<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Tests\Component\Form\Extension\Csrf\Type;

class FormTypeCsrfExtensionTest extends TypeTestCase
{
    public function testCsrfProtectionByDefault()
    {
        $form =  $this->factory->create('form', null, array(
            'csrf_field_name' => 'csrf',
        ));

        $this->assertTrue($form->has('csrf'));
    }

    public function testCsrfProtectionCanBeDisabled()
    {
        $form =  $this->factory->create('form', null, array(
            'csrf_protection' => false,
        ));

        $this->assertEquals(0, count($form));
    }
}
