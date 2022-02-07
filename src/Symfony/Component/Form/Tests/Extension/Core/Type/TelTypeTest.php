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

class TelTypeTest extends BaseTypeTest
{
    public const TESTED_TYPE = 'Symfony\Component\Form\Extension\Core\Type\TelType';

    public function testSubmitNull($expected = null, $norm = null, $view = null)
    {
        parent::testSubmitNull($expected, $norm, '');
    }

    public function testPlaceholderOption()
    {
        $placeholder_option_text = 'my placeholder...';
        $form = $this->factory->create(static::TESTED_TYPE, null, [
            'placeholder' => $placeholder_option_text,
        ]);
        $this->assertSame($placeholder_option_text, $form->createView()->vars['attr']['placeholder']);
    }
}
