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
    public function testSubmitEmptyDataAsEmptyString()
    {
        $form = $this->factory->create('text', null, array(
            'required' => false,
            'empty_data' => '',
        ));

        $form->submit(null);

        $this->assertSame('', $form->getData());
        $this->assertSame('', $form->getViewData());
    }

    public function testSubmitNullData()
    {
        $form = $this->factory->create('text', null, array(
            'required' => false,
        ));

        $form->submit(null);

        $this->assertNull($form->getData());
        $this->assertSame('', $form->getViewData());
    }

    public function testSubmitNullDataAsNull()
    {
        $form = $this->factory->create('text', null, array(
            'required' => false,
            'empty_data' => null,
        ));

        $form->submit(null);

        $this->assertNull($form->getData());
        $this->assertSame('', $form->getViewData());
    }

    public function testSubmitEmptyDataAsNull()
    {
        $form = $this->factory->create('text', null, array(
            'required' => false,
            'empty_data' => null,
        ));

        $form->submit('');

        $this->assertNull($form->getData());
        $this->assertSame('', $form->getViewData());
    }
}
