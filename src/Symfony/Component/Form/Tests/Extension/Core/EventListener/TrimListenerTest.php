<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Form\Tests\Extension\Core\EventListener;

use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\Extension\Core\EventListener\TrimListener;

class TrimListenerTest extends \PHPUnit_Framework_TestCase
{
    protected function setUp()
    {
        if (!class_exists('Symfony\Component\EventDispatcher\EventDispatcher')) {
            $this->markTestSkipped('The "EventDispatcher" component is not available');
        }
    }

    public function testTrim()
    {
        $data = " Foo! ";
        $form = $this->getMock('Symfony\Component\Form\Test\FormInterface');
        $event = new FormEvent($form, $data);

        $filter = new TrimListener();
        $filter->preSubmit($event);

        $this->assertEquals('Foo!', $event->getData());
    }

    public function testTrimSkipNonStrings()
    {
        $data = 1234;
        $form = $this->getMock('Symfony\Component\Form\Test\FormInterface');
        $event = new FormEvent($form, $data);

        $filter = new TrimListener();
        $filter->preSubmit($event);

        $this->assertSame(1234, $event->getData());
    }

    /**
     * @dataProvider spaceProvider
     */
    public function testTrimUtf8Separators($hex)
    {
        if (!function_exists('mb_convert_encoding')) {
            $this->markTestSkipped('The "mb_convert_encoding" function is not available');
        }

        // Convert hexadecimal representation into binary
        // H: hex string, high nibble first (UCS-2BE)
        // *: repeat until end of string
        $binary = pack('H*', $hex);

        // Convert UCS-2BE to UTF-8
        $symbol = mb_convert_encoding($binary, 'UTF-8', 'UCS-2BE');
        $symbol = $symbol."ab\ncd".$symbol;

        $form  = $this->getMock('Symfony\Component\Form\Test\FormInterface');
        $event = new FormEvent($form, $symbol);

        $filter = new TrimListener();
        $filter->preSubmit($event);

        $this->assertSame("ab\ncd", $event->getData());
    }

    public function spaceProvider()
    {
        return array(
            // separators
            array('0020'),
            array('00A0'),
            array('1680'),
//            array('180E'),
            array('2000'),
            array('2001'),
            array('2002'),
            array('2003'),
            array('2004'),
            array('2005'),
            array('2006'),
            array('2007'),
            array('2008'),
            array('2009'),
            array('200A'),
            array('2028'),
            array('2029'),
            array('202F'),
            array('205F'),
            array('3000'),
            // controls
            array('0009'),
            array('000A'),
            array('000B'),
            array('000C'),
            array('000D'),
            array('0085'),
            // zero width space
//            array('200B'),
        );
    }
}
