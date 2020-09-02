<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HttpKernel\Tests\DataCollector;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Debug\Exception\SilencedErrorContext;
use Symfony\Component\HttpKernel\DataCollector\LoggerDataCollector;

class LoggerDataCollectorTest extends TestCase
{
    public function testCollectWithUnexpectedFormat()
    {
        $logger = $this
            ->getMockBuilder('Symfony\Component\HttpKernel\Log\DebugLoggerInterface')
            ->setMethods(['countErrors', 'getLogs', 'clear'])
            ->getMock();
        $logger->expects($this->once())->method('countErrors')->willReturn(123);
        $logger->expects($this->exactly(2))->method('getLogs')->willReturn([]);

        $c = new LoggerDataCollector($logger, __DIR__.'/');
        $c->lateCollect();
        $compilerLogs = $c->getCompilerLogs()->getValue('message');

        $this->assertSame([
            ['message' => 'Removed service "Psr\Container\ContainerInterface"; reason: private alias.'],
            ['message' => 'Removed service "Symfony\Component\DependencyInjection\ContainerInterface"; reason: private alias.'],
        ], $compilerLogs['Symfony\Component\DependencyInjection\Compiler\RemovePrivateAliasesPass']);

        $this->assertSame([
            ['message' => 'Some custom logging message'],
            ['message' => 'With ending :'],
        ], $compilerLogs['Unknown Compiler Pass']);
    }

    /**
     * @dataProvider getCollectTestData
     */
    public function testCollect($nb, $logs, $expectedLogs, $expectedDeprecationCount, $expectedScreamCount, $expectedPriorities = null)
    {
        $logger = $this
            ->getMockBuilder('Symfony\Component\HttpKernel\Log\DebugLoggerInterface')
            ->setMethods(['countErrors', 'getLogs', 'clear'])
            ->getMock();
        $logger->expects($this->once())->method('countErrors')->willReturn($nb);
        $logger->expects($this->exactly(2))->method('getLogs')->willReturn($logs);

        $c = new LoggerDataCollector($logger);
        $c->lateCollect();

        $this->assertEquals('logger', $c->getName());
        $this->assertEquals($nb, $c->countErrors());

        $logs = array_map(function ($v) {
            if (isset($v['context']['exception'])) {
                $e = &$v['context']['exception'];
                $e = isset($e["\0*\0message"]) ? [$e["\0*\0message"], $e["\0*\0severity"]] : [$e["\0Symfony\Component\Debug\Exception\SilencedErrorContext\0severity"]];
            }

            return $v;
        }, $c->getLogs()->getValue(true));
        $this->assertEquals($expectedLogs, $logs);
        $this->assertEquals($expectedDeprecationCount, $c->countDeprecations());
        $this->assertEquals($expectedScreamCount, $c->countScreams());

        if (isset($expectedPriorities)) {
            $this->assertSame($expectedPriorities, $c->getPriorities()->getValue(true));
        }
    }

    public function testReset()
    {
        $logger = $this
            ->getMockBuilder('Symfony\Component\HttpKernel\Log\DebugLoggerInterface')
            ->setMethods(['countErrors', 'getLogs', 'clear'])
            ->getMock();
        $logger->expects($this->once())->method('clear');

        $c = new LoggerDataCollector($logger);
        $c->reset();
    }

    public function getCollectTestData()
    {
        yield 'simple log' => [
            1,
            [['message' => 'foo', 'context' => [], 'priority' => 100, 'priorityName' => 'DEBUG']],
            [['message' => 'foo', 'context' => [], 'priority' => 100, 'priorityName' => 'DEBUG']],
            0,
            0,
        ];

        yield 'log with a context' => [
            1,
            [['message' => 'foo', 'context' => ['foo' => 'bar'], 'priority' => 100, 'priorityName' => 'DEBUG']],
            [['message' => 'foo', 'context' => ['foo' => 'bar'], 'priority' => 100, 'priorityName' => 'DEBUG']],
            0,
            0,
        ];

        if (!class_exists(SilencedErrorContext::class)) {
            return;
        }

        yield 'logs with some deprecations' => [
            1,
            [
                ['message' => 'foo3', 'context' => ['exception' => new \ErrorException('warning', 0, \E_USER_WARNING)], 'priority' => 100, 'priorityName' => 'DEBUG'],
                ['message' => 'foo', 'context' => ['exception' => new \ErrorException('deprecated', 0, \E_DEPRECATED)], 'priority' => 100, 'priorityName' => 'DEBUG'],
                ['message' => 'foo2', 'context' => ['exception' => new \ErrorException('deprecated', 0, \E_USER_DEPRECATED)], 'priority' => 100, 'priorityName' => 'DEBUG'],
            ],
            [
                ['message' => 'foo3', 'context' => ['exception' => ['warning', \E_USER_WARNING]], 'priority' => 100, 'priorityName' => 'DEBUG'],
                ['message' => 'foo', 'context' => ['exception' => ['deprecated', \E_DEPRECATED]], 'priority' => 100, 'priorityName' => 'DEBUG', 'errorCount' => 1, 'scream' => false],
                ['message' => 'foo2', 'context' => ['exception' => ['deprecated', \E_USER_DEPRECATED]], 'priority' => 100, 'priorityName' => 'DEBUG', 'errorCount' => 1, 'scream' => false],
            ],
            2,
            0,
            [100 => ['count' => 3, 'name' => 'DEBUG']],
        ];

        yield 'logs with some silent errors' => [
            1,
            [
                ['message' => 'foo3', 'context' => ['exception' => new \ErrorException('warning', 0, \E_USER_WARNING)], 'priority' => 100, 'priorityName' => 'DEBUG'],
                ['message' => 'foo3', 'context' => ['exception' => new SilencedErrorContext(\E_USER_WARNING, __FILE__, __LINE__)], 'priority' => 100, 'priorityName' => 'DEBUG'],
                ['message' => '0', 'context' => ['exception' => new SilencedErrorContext(\E_USER_WARNING, __FILE__, __LINE__)], 'priority' => 100, 'priorityName' => 'DEBUG'],
            ],
            [
                ['message' => 'foo3', 'context' => ['exception' => ['warning', \E_USER_WARNING]], 'priority' => 100, 'priorityName' => 'DEBUG'],
                ['message' => 'foo3', 'context' => ['exception' => [\E_USER_WARNING]], 'priority' => 100, 'priorityName' => 'DEBUG', 'errorCount' => 1, 'scream' => true],
                ['message' => '0', 'context' => ['exception' => [\E_USER_WARNING]], 'priority' => 100, 'priorityName' => 'DEBUG', 'errorCount' => 1, 'scream' => true],
            ],
            0,
            2,
        ];
    }
}
