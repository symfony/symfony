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
    public function testIntlWithouRegion()
    {
        $form = $this->factory->create('tel');
        $form->submit('+33908998767');
        $view = $form->createView();
        $this->assertSame('+33908998767', $view->vars['value']);
    }

    public function testLocalWithouRegion()
    {
        $form = $this->factory->create('tel');
        $form->submit('0289009098');
        $view = $form->createView();

        $this->assertSame('0289009098', $view->vars['value']);
    }

    public function testRegionAsLocal()
    {
        $form = $this->factory->create('tel', null, array('region' => "FR"));
        $form->submit('0289009098');
        $view = $form->createView();

        $this->assertSame('+33289009098', $view->vars['value']);
    }

    public function testRegionAsIntl()
    {
        $form = $this->factory->create('tel', null, array('region' => "FR"));
        $form->submit('+33289009098');
        $view = $form->createView();

        $this->assertSame('+33289009098', $view->vars['value']);
    }
}
