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
        $logger = $this->getMock('Symfony\\Component\\HttpKernel\\Log\\LoggerInterface');

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
            array('SQL', array('foo' => 'bar'), array('foo' => 'bar'))
        );
    }

    public function testLogNonUtf8()
    {
        $logger = $this->getMock('Symfony\\Component\\HttpKernel\\Log\\LoggerInterface');

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
            'utf8'    => 'foo',
            'nonutf8' => "\x7F\xFF",
        ));
    }

    public function testLogLongString()
    {
        $logger = $this->getMock('Symfony\\Component\\HttpKernel\\Log\\LoggerInterface');

        $dbalLogger = $this
            ->getMockBuilder('Symfony\\Bridge\\Doctrine\\Logger\\DbalLogger')
            ->setConstructorArgs(array($logger, null))
            ->setMethods(array('log'))
            ->getMock()
        ;

        $shortString = str_repeat('a', DbalLogger::MAX_STRING_LENGTH);
        $longString = str_repeat('a', DbalLogger::MAX_STRING_LENGTH + 1);

        $dbalLogger
            ->expects($this->once())
            ->method('log')
            ->with('SQL', array('short' => $shortString, 'long' => substr($longString, DbalLogger::MAX_STRING_LENGTH - 6).' [...]'))
        ;

        $dbalLogger->startQuery('SQL', array(
            'short' => $shortString,
            'long'  => $longString,
        ));
    }
}
