<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Form\Tests\Extension\Core\Type;

use Symfony\Component\Form\Test\TypeTestCase as TestCase;

class TextTypeTest extends TestCase
{
    public function testSubmitNullReturnsNull()
    {
        $form = $this->factory->create('Symfony\Component\Form\Extension\Core\Type\TextType', 'name');

        $form->submit(null);

        $this->assertNull($form->getData());
    }

    public function testSubmitNullReturnsEmptyStringWithEmptyDataAsString()
    {
        $form = $this->factory->create('Symfony\Component\Form\Extension\Core\Type\TextType', 'name', array(
            'empty_data' => '',
        ));

        $form->submit(null);

        $this->assertSame('', $form->getData());
    }
}
