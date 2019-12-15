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

class SearchTypeTest extends BaseTypeTest
{
    const TESTED_TYPE = 'Symfony\Component\Form\Extension\Core\Type\SearchType';

    public function testDefaultInputmode()
    {
        $form = $this->factory->create(static::TESTED_TYPE);

        $this->assertSame('search', $form->createView()->vars['attr']['inputmode']);
    }

    public function testOverwrittenInputmode()
    {
        $form = $this->factory->create(static::TESTED_TYPE, null, ['attr' => ['inputmode' => 'text']]);

        $this->assertSame('text', $form->createView()->vars['attr']['inputmode']);
    }

    public function testSubmitNull($expected = null, $norm = null, $view = null)
    {
        parent::testSubmitNull($expected, $norm, '');
    }
}
