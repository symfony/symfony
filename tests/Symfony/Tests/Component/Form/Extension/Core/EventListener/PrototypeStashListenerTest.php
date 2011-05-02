<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Tests\Component\Form\Extension\Core\EventListener;

use Symfony\Component\Form\Extension\Core\EventListener\PrototypeStashListener;

class PrototypeStashListenerTest extends \PHPUnit_Framework_TestCase
{
    public function testStash()
    {
        $event = $this->getMockBuilder('Symfony\\Component\\Form\\Event\\DataEvent')
            ->disableOriginalConstructor()
            ->getMock();
        $form = $this->getMockBuilder('Symfony\\Component\\Form\\Form')
            ->disableOriginalConstructor()
            ->getMock();
        $field = $this->getMockBuilder('Symfony\\Component\\Form\\Form')
            ->disableOriginalConstructor()
            ->getMock();

        $event->expects($this->any())
            ->method('getForm')
            ->will($this->returnValue($form));
        $form->expects($this->once())
            ->method('offsetGet')
            ->with('$$name$$')
            ->will($this->returnValue($field));
        $form->expects($this->once())
            ->method('offsetUnset')
            ->with('$$name$$');

        $listener = new PrototypeStashListener();
        $listener->preBind($event);

        $form->expects($this->once())
            ->method('offsetSet')
            ->with('$$name$$', $field);

        $listener->postBind($event);
    }
}
