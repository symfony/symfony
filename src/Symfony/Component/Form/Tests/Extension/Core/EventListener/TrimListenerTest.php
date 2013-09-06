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
     * @dataProvider codePointProvider
     */
    public function testTrimUtf8($chars)
    {
        if (!function_exists('mb_check_encoding')) {
            $this->markTestSkipped('The "mb_check_encoding" function is not available');
        }

        $data = mb_convert_encoding(pack('H*', implode('', $chars)), 'UTF-8', 'UCS-2BE');
        $data = $data."ab\ncd".$data;

        $form  = $this->getMock('Symfony\Component\Form\Test\FormInterface');
        $event = new FormEvent($form, $data);

        $filter = new TrimListener();
        $filter->preSubmit($event);

        $this->assertSame("ab\ncd", $event->getData(), 'TrimListener should trim character(s): '.implode(', ', $chars));
    }

    public function codePointProvider()
    {
        return array(
            'General category: Separator' => array(array('0020', '00A0', '1680', '180E', '2000', '2001', '2002', '2003', '2004', '2005', '2006', '2007', '2008', '2009', '200A', '2028', '2029', '202F', '205F', '3000')),
            'General category: Other, control' => array(array('0009', '000A', '000B', '000C', '000D', '0085')),
            //'General category: Other, format. ZERO WIDTH SPACE' => array(array('200B')),
        );
    }
}
