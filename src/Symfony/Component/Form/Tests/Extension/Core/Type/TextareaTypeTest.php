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

class TextareaTypeTest extends TypeTestCase
{
    /**
     * Textarea do not have pattern html attribute
     */
    public function testThatCannotSetPatternAttribute()
    {
        $form  = $this->factory->create('textarea', null, array('pattern' => 'somepattern'));
        $view = $form->createView();

        $this->assertNull($view->get('pattern'));
    }
}
