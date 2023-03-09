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
use Psr\Log\LoggerInterface;
use Symfony\Bridge\Doctrine\Logger\DbalLogger;

/**
 * @group legacy
 */
class DbalLoggerTest extends TestCase
{
    /**
     * @dataProvider getLogFixtures
     */
    public function testLog($sql, $params, $logParams)
    {
        $logger = $this->createMock(LoggerInterface::class);

        $dbalLogger = $this
            ->getMockBuilder(DbalLogger::class)
            ->setConstructorArgs([$logger, null])
            ->onlyMethods(['log'])
            ->getMock()
        ;

        $dbalLogger
            ->expects($this->once())
            ->method('log')
            ->with($sql, $logParams)
        ;

        $dbalLogger->startQuery($sql, $params);
    }

    public static function getLogFixtures()
    {
        return [
            ['SQL', null, []],
            ['SQL', [], []],
            ['SQL', ['foo' => 'bar'], ['foo' => 'bar']],
            ['SQL', ['foo' => "\x7F\xFF"], ['foo' => '(binary value)']],
            ['SQL', ['foo' => "bar\x7F\xFF"], ['foo' => '(binary value)']],
            ['SQL', ['foo' => ''], ['foo' => '']],
        ];
    }

    public function testLogNonUtf8()
    {
        $logger = $this->createMock(LoggerInterface::class);

        $dbalLogger = $this
            ->getMockBuilder(DbalLogger::class)
            ->setConstructorArgs([$logger, null])
            ->onlyMethods(['log'])
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
        $logger = $this->createMock(LoggerInterface::class);

        $dbalLogger = $this
            ->getMockBuilder(DbalLogger::class)
            ->setConstructorArgs([$logger, null])
            ->onlyMethods(['log'])
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
        $logger = $this->createMock(LoggerInterface::class);

        $dbalLogger = $this
            ->getMockBuilder(DbalLogger::class)
            ->setConstructorArgs([$logger, null])
            ->onlyMethods(['log'])
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
        $logger = $this->createMock(LoggerInterface::class);

        $dbalLogger = $this
            ->getMockBuilder(DbalLogger::class)
            ->setConstructorArgs([$logger, null])
            ->onlyMethods(['log'])
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
