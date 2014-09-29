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
    public function testFormat($expected, $message, $number, $arguments)
    {
        $formatter = new DefaultMessageFormatter();

        $this->assertEquals($expected, $formatter->format('en', $message, $number, $arguments));
    }

    public function provideDataForFormat()
    {
        return array(
            array(
                'There is one apple',
                'There is one apple',
                null,
                array(),
            ),
            array(
                'There are 5 apples',
                'There are %count% apples',
                null,
                array('%count%' => 5),
            ),
            array(
                'There are 10 apples',
                '{0} There are no apples|{1} There is one apple|]1,Inf[ There are %count% apples',
                10,
                array('%count%' => 10),
            ),
        );
    }

    /**
     * @dataProvider provideDataForFormatShouldCallChooseWithNumber
     */
    public function testFormatShouldCallChooseWithNumber($willCallChoose, $number)
    {
        $selector  = $this->mockMessageSelector($willCallChoose);
        $formatter = new DefaultMessageFormatter($selector);

        $formatter->format('en', 'message_id', $number, array());
    }

    public function provideDataForFormatShouldCallChooseWithNumber()
    {
        return array(
            array(false, null),
            array(true, 2),
        );
    }

    private function mockMessageSelector($willCallChoose)
    {
        $mock = $this->getMock('Symfony\Component\Translation\Formatter\MessageSelector');

        $mock->expects($willCallChoose ? $this->once() : $this->never())
             ->method('choose')
             ->will($this->returnValue('Message'));

        return $mock;
    }
}