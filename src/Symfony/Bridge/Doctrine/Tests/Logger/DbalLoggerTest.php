<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bridge\Doctrine\Tests\Logger;

use Symfony\Bridge\Doctrine\Logger\DbalLogger;

class DbalLoggerTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @dataProvider getLogFixtures
     */
    public function testLog($sql, $params, $logParams)
    {
        $logger = $this->getMock('Psr\\Log\\LoggerInterface');

        $dbalLogger = $this
            ->getMockBuilder('Symfony\\Bridge\\Doctrine\\Logger\\DbalLogger')
            ->setConstructorArgs(array($logger, null))
            ->setMethods(array('log'))
            ->getMock()
        ;

        $dbalLogger
            ->expects($this->once())
            ->method('log')
            ->with($sql, $logParams)
        ;

        $dbalLogger->startQuery($sql, $params);
    }

    public function getLogFixtures()
    {
        return array(
            array('SQL', null, array()),
            array('SQL', array(), array()),
            array('SQL', array('foo' => 'bar'), array('foo' => 'bar')),
            array('SQL', array('foo' => "\x7F\xFF"), array('foo' => DbalLogger::BINARY_DATA_VALUE)),
            array('SQL', array('foo' => "bar\x7F\xFF"), array('foo' => DbalLogger::BINARY_DATA_VALUE)),
            array('SQL', array('foo' => ''), array('foo' => '')),
        );
    }

    public function testLogNonUtf8()
    {
        $logger = $this->getMock('Psr\\Log\\LoggerInterface');

        $dbalLogger = $this
            ->getMockBuilder('Symfony\\Bridge\\Doctrine\\Logger\\DbalLogger')
            ->setConstructorArgs(array($logger, null))
            ->setMethods(array('log'))
            ->getMock()
        ;

        $dbalLogger
            ->expects($this->once())
            ->method('log')
            ->with('SQL', array('utf8' => 'foo', 'nonutf8' => DbalLogger::BINARY_DATA_VALUE))
        ;

        $dbalLogger->startQuery('SQL', array(
            'utf8' => 'foo',
            'nonutf8' => "\x7F\xFF",
        ));
    }

    public function testLogNonUtf8Array()
    {
        $logger = $this->getMock('Psr\\Log\\LoggerInterface');

        $dbalLogger = $this
            ->getMockBuilder('Symfony\\Bridge\\Doctrine\\Logger\\DbalLogger')
            ->setConstructorArgs(array($logger, null))
            ->setMethods(array('log'))
            ->getMock()
        ;

        $dbalLogger
            ->expects($this->once())
            ->method('log')
            ->with('SQL', array(
                    'utf8' => 'foo',
                    array(
                        'nonutf8' => DbalLogger::BINARY_DATA_VALUE,
                    ),
                )
            )
        ;

        $dbalLogger->startQuery('SQL', array(
            'utf8' => 'foo',
            array(
                'nonutf8' => "\x7F\xFF",
            ),
        ));
    }

    public function testLogLongString()
    {
        $logger = $this->getMock('Psr\\Log\\LoggerInterface');

        $dbalLogger = $this
            ->getMockBuilder('Symfony\\Bridge\\Doctrine\\Logger\\DbalLogger')
            ->setConstructorArgs(array($logger, null))
            ->setMethods(array('log'))
            ->getMock()
        ;

        $testString = 'abc';

        $shortString = str_pad('', DbalLogger::MAX_STRING_LENGTH, $testString);
        $longString = str_pad('', DbalLogger::MAX_STRING_LENGTH + 1, $testString);

        $dbalLogger
            ->expects($this->once())
            ->method('log')
            ->with('SQL', array('short' => $shortString, 'long' => substr($longString, 0, DbalLogger::MAX_STRING_LENGTH - 6).' [...]'))
        ;

        $dbalLogger->startQuery('SQL', array(
            'short' => $shortString,
            'long' => $longString,
        ));
    }

    /**
     * @requires extension mbstring
     */
    public function testLogUTF8LongString()
    {
        $logger = $this->getMock('Psr\\Log\\LoggerInterface');

        $dbalLogger = $this
            ->getMockBuilder('Symfony\\Bridge\\Doctrine\\Logger\\DbalLogger')
            ->setConstructorArgs(array($logger, null))
            ->setMethods(array('log'))
            ->getMock()
        ;

        $testStringArray = array('é', 'á', 'ű', 'ő', 'ú', 'ö', 'ü', 'ó', 'í');
        $testStringCount = count($testStringArray);

        $shortString = '';
        $longString = '';
        for ($i = 1; $i <= DbalLogger::MAX_STRING_LENGTH; ++$i) {
            $shortString .= $testStringArray[$i % $testStringCount];
            $longString .= $testStringArray[$i % $testStringCount];
        }
        $longString .= $testStringArray[$i % $testStringCount];

        $dbalLogger
            ->expects($this->once())
            ->method('log')
            ->with('SQL', array('short' => $shortString, 'long' => mb_substr($longString, 0, DbalLogger::MAX_STRING_LENGTH - 6, mb_detect_encoding($longString)).' [...]'))
        ;

        $dbalLogger->startQuery('SQL', array(
                'short' => $shortString,
                'long' => $longString,
            ));
    }
}
