<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Translation\Tests\Formatter;

use Symfony\Component\Translation\Formatter\DefaultMessageFormatter;

class DefaultMessageFormatterTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @dataProvider provideDataForFormat
     */
    public function testFormat($expected, $message, $arguments)
    {
        $formatter = new DefaultMessageFormatter();

        $this->assertEquals($expected, $formatter->format('en', $message, $arguments));
    }

    public function provideDataForFormat()
    {
        return array(
            array(
                'There is one apple',
                'There is one apple',
                array(),
            ),
            array(
                'There are 5 apples',
                'There are %count% apples',
                array('%count%' => 5),
            ),
        );
    }

    private function mockMessageSelector($willCallChoose)
    {
        $mock = $this->getMock('Symfony\Component\Translation\MessageSelector');

        $mock->expects($willCallChoose ? $this->once() : $this->never())
             ->method('choose')
             ->will($this->returnValue('Message'));

        return $mock;
    }
}
