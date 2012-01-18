<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Tests\Component\Form\Extension\Validator\Type;

use Symfony\Component\Form\FormInterface;

class FieldTypeHelpExtensionTest extends TypeTestCase
{
    public function testHelpNullByDefault()
    {
        $form =  $this->factory->create('field');

        $this->assertNull($form->getAttribute('help'));
    }

    public function testHelpCanBeSetToString()
    {
        $form = $this->factory->create('field', null, array(
            'help' => 'message',
        ));

        $this->assertEquals('message', $form->getAttribute('help'));
    }
}
