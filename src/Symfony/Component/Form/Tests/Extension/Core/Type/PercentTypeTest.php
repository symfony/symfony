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

class PercentTypeTest extends TypeTestCase
{
    public function testThatHandleFractionalType()
    {
        $form = $this->factory->create('percent', null, array(
            'type' => 'fractional',
        ));

        $form->bind(55);
        $view = $form->createView();

        $this->assertEquals(.55, $form->getData());
        $this->assertEquals(55, $view->get('value'));
    }

    public function testThatHandleIntegerType()
    {
        $form = $this->factory->create('percent', null, array(
            'type' => 'integer',
        ));

        $form->bind(55);
        $view = $form->createView();

        $this->assertEquals(55, $form->getData());
        $this->assertEquals(55, $view->get('value'));
    }
}
