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

class EmailTypeTest extends TypeTestCase
{
    public function testThatCanUseEmailType()
    {
        $form = $this->factory->create('email');

        $form->bind('leszek.prabucki@gmail.com');
        $view = $form->createView();

        $this->assertEquals('leszek.prabucki@gmail.com', $form->getData());
        $this->assertEquals('leszek.prabucki@gmail.com', $view->get('value'));
    }
}
