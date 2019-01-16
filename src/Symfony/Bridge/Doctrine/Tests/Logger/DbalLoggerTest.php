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

use PHPUnit\Framework\TestCase;
use Symfony\Bridge\Doctrine\Logger\DbalLogger;

class DbalLoggerTest extends TestCase
{
    /**
     * @dataProvider getLogFixtures
     */
    public function testLog($sql, $params, $logParams)
    {
        $logger = $this->getMockBuilder('Psr\\Log\\LoggerInterface')->getMock();

        $dbalLogger = $this
            ->getMockBuilder('Symfony\\Bridge\\Doctrine\\Logger\\DbalLogger')
            ->setConstructorArgs([$logger, null])
            ->setMethods(['log'])
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
        return [
            ['SQL', null, []],
            ['SQL', [], []],
            ['SQL', ['foo' => 'bar'], ['foo' => 'bar']],
            ['SQL', ['foo' => "\x7F\xFF"], ['foo' => DbalLogger::BINARY_DATA_VALUE]],
            ['SQL', ['foo' => "bar\x7F\xFF"], ['foo' => DbalLogger::BINARY_DATA_VALUE]],
            ['SQL', ['foo' => ''], ['foo' => '']],
        ];
    }

    public function testLogNonUtf8()
    {
        $logger = $this->getMockBuilder('Psr\\Log\\LoggerInterface')->getMock();

        $dbalLogger = $this
            ->getMockBuilder('Symfony\\Bridge\\Doctrine\\Logger\\DbalLogger')
            ->setConstructorArgs([$logger, null])
            ->setMethods(['log'])
            ->getMock()
        ;

        $dbalLogger
            ->expects($this->once())
            ->method('log')
            ->with('SQL', ['utf8' => 'foo', 'nonutf8' => DbalLogger::BINARY_DATA_VALUE])
        ;

        $dbalLogger->startQuery('SQL', [
            'utf8' => 'foo',
            'nonutf8' => "\x7F\xFF",
        ]);
    }

    public function testLogNonUtf8Array()
    {
        $logger = $this->getMockBuilder('Psr\\Log\\LoggerInterface')->getMock();

        $dbalLogger = $this
            ->getMockBuilder('Symfony\\Bridge\\Doctrine\\Logger\\DbalLogger')
            ->setConstructorArgs([$logger, null])
            ->setMethods(['log'])
            ->getMock()
        ;

        $dbalLogger
            ->expects($this->once())
            ->method('log')
            ->with('SQL', [
                    'utf8' => 'foo',
                    [
                        'nonutf8' => DbalLogger::BINARY_DATA_VALUE,
                    ],
                ]
            )
        ;

        $dbalLogger->startQuery('SQL', [
            'utf8' => 'foo',
            [
                'nonutf8' => "\x7F\xFF",
            ],
        ]);
    }

    public function testLogLongString()
    {
        $logger = $this->getMockBuilder('Psr\\Log\\LoggerInterface')->getMock();

        $dbalLogger = $this
            ->getMockBuilder('Symfony\\Bridge\\Doctrine\\Logger\\DbalLogger')
            ->setConstructorArgs([$logger, null])
            ->setMethods(['log'])
            ->getMock()
        ;

        $testString = 'abc';

        $shortString = str_pad('', DbalLogger::MAX_STRING_LENGTH, $testString);
        $longString = str_pad('', DbalLogger::MAX_STRING_LENGTH + 1, $testString);

        $dbalLogger
            ->expects($this->once())
            ->method('log')
            ->with('SQL', ['short' => $shortString, 'long' => substr($longString, 0, DbalLogger::MAX_STRING_LENGTH - 6).' [...]'])
        ;

        $dbalLogger->startQuery('SQL', [
            'short' => $shortString,
            'long' => $longString,
        ]);
    }

    public function testLogUTF8LongString()
    {
        $logger = $this->getMockBuilder('Psr\\Log\\LoggerInterface')->getMock();

        $dbalLogger = $this
            ->getMockBuilder('Symfony\\Bridge\\Doctrine\\Logger\\DbalLogger')
            ->setConstructorArgs([$logger, null])
            ->setMethods(['log'])
            ->getMock()
        ;

        $testStringArray = ['é', 'á', 'ű', 'ő', 'ú', 'ö', 'ü', 'ó', 'í'];
        $testStringCount = \count($testStringArray);

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
            ->with('SQL', ['short' => $shortString, 'long' => mb_substr($longString, 0, DbalLogger::MAX_STRING_LENGTH - 6, 'UTF-8').' [...]'])
        ;

        $dbalLogger->startQuery('SQL', [
                'short' => $shortString,
                'long' => $longString,
            ]);
    }
}
