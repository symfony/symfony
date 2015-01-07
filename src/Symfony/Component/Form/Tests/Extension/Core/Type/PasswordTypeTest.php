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

class PasswordTypeTest extends \Symfony\Component\Form\Test\TypeTestCase
{
    public function testEmptyIfNotSubmitted()
    {
        $form = $this->factory->create('password');
        $form->setData('pAs5w0rd');
        $view = $form->createView();

        $this->assertSame('', $view->vars['value']);
    }

    public function testEmptyIfSubmitted()
    {
        $form = $this->factory->create('password');
        $form->submit('pAs5w0rd');
        $view = $form->createView();

        $this->assertSame('', $view->vars['value']);
    }

    public function testNotEmptyIfSubmittedAndNotAlwaysEmpty()
    {
        $form = $this->factory->create('password', array('always_empty' => false));
        $form->submit('pAs5w0rd');
        $view = $form->createView();

        $this->assertSame('pAs5w0rd', $view->vars['value']);
    }

    public function testNotTrimmed()
    {
        $form = $this->factory->create('password');
        $form->submit(' pAs5w0rd ');
        $data = $form->getData();

        $this->assertSame(' pAs5w0rd ', $data);
    }
}
