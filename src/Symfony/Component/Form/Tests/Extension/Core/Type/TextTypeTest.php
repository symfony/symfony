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

class TextTypeTest extends TypeTestCase
{
    public function testDontPassPlaceholderValue()
    {
        $form = $this->factory->create('text');
        $view = $form->createView();

        $this->assertNull($view->vars['placeholder']);
    }

    public function testPassPlaceholderValue()
    {
        $form = $this->factory->create('text', null, array('placeholder' => 'Empty Text'));
        $view = $form->createView();

        $this->assertSame('Empty Text', $view->vars['placeholder']);
    }

    public function testPassPlaceholderValueAsAttribute()
    {
        $form = $this->factory->create('text', null, array(
            'attr' => array('placeholder' => 'Empty Text')
        ));
        $view = $form->createView();

        $this->assertSame('Empty Text', $view->vars['placeholder']);
    }
}
