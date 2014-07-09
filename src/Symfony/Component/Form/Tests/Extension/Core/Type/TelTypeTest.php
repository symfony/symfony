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

class TelTypeTest extends \Symfony\Component\Form\Test\TypeTestCase
{
    public function testIntl()
    {
        $form = $this->factory->create('tel');
        $form->submit('+33908998767');
        $view = $form->createView();
        $this->assertSame('+33908998767', $view->vars['value']);
    }

    public function testLocal()
    {
        $form = $this->factory->create('tel');
        $form->submit('0289009098');
        $view = $form->createView();

        $this->assertSame('0289009098', $view->vars['value']);
    }
}

