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
        $logger = $this->getMockBuilder('Symfony\Component\HttpKernel\Log\DebugLoggerInterface')->getMock();
        $logger->expects($this->once())->method('countErrors')->will($this->returnValue('foo'));
        $logger->expects($this->exactly(2))->method('getLogs')->will($this->returnValue(array()));

        $c = new LoggerDataCollector($logger, __DIR__.'/');
        $c->lateCollect();
        $compilerLogs = $c->getCompilerLogs()->getValue('message');

        $this->assertSame(array(
            array('message' => 'Removed service "Psr\Container\ContainerInterface"; reason: private alias.'),
            array('message' => 'Removed service "Symfony\Component\DependencyInjection\ContainerInterface"; reason: private alias.'),
        ), $compilerLogs['Symfony\Component\DependencyInjection\Compiler\RemovePrivateAliasesPass']);

        $this->assertSame(array(
            array('message' => 'Some custom logging message'),
            array('message' => 'With ending :'),
        ), $compilerLogs['Unknown Compiler Pass']);
    }

    /**
     * @dataProvider getCollectTestData
     */
    public function testCollect($nb, $logs, $expectedLogs, $expectedDeprecationCount, $expectedScreamCount, $expectedPriorities = null)
    {
        $logger = $this->getMockBuilder('Symfony\Component\HttpKernel\Log\DebugLoggerInterface')->getMock();
        $logger->expects($this->once())->method('countErrors')->will($this->returnValue($nb));
        $logger->expects($this->exactly(2))->method('getLogs')->will($this->returnValue($logs));

        $c = new LoggerDataCollector($logger);
        $c->lateCollect();

        $this->assertEquals('logger', $c->getName());
        $this->assertEquals($nb, $c->countErrors());

        $logs = array_map(function ($v) {
            if (isset($v['context']['exception'])) {
                $e = &$v['context']['exception'];
                $e = isset($e["\0*\0message"]) ? array($e["\0*\0message"], $e["\0*\0severity"]) : array($e["\0Symfony\Component\Debug\Exception\SilencedErrorContext\0severity"]);
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

    public function getCollectTestData()
    {
        yield 'simple log' => array(
            1,
            array(array('message' => 'foo', 'context' => array(), 'priority' => 100, 'priorityName' => 'DEBUG')),
            array(array('message' => 'foo', 'context' => array(), 'priority' => 100, 'priorityName' => 'DEBUG')),
            0,
            0,
        );

        yield 'log with a context' => array(
            1,
            array(array('message' => 'foo', 'context' => array('foo' => 'bar'), 'priority' => 100, 'priorityName' => 'DEBUG')),
            array(array('message' => 'foo', 'context' => array('foo' => 'bar'), 'priority' => 100, 'priorityName' => 'DEBUG')),
            0,
            0,
        );

        if (!class_exists(SilencedErrorContext::class)) {
            return;
        }

        yield 'logs with some deprecations' => array(
            1,
            array(
                array('message' => 'foo3', 'context' => array('exception' => new \ErrorException('warning', 0, E_USER_WARNING)), 'priority' => 100, 'priorityName' => 'DEBUG'),
                array('message' => 'foo', 'context' => array('exception' => new \ErrorException('deprecated', 0, E_DEPRECATED)), 'priority' => 100, 'priorityName' => 'DEBUG'),
                array('message' => 'foo2', 'context' => array('exception' => new \ErrorException('deprecated', 0, E_USER_DEPRECATED)), 'priority' => 100, 'priorityName' => 'DEBUG'),
            ),
            array(
                array('message' => 'foo3', 'context' => array('exception' => array('warning', E_USER_WARNING)), 'priority' => 100, 'priorityName' => 'DEBUG'),
                array('message' => 'foo', 'context' => array('exception' => array('deprecated', E_DEPRECATED)), 'priority' => 100, 'priorityName' => 'DEBUG', 'errorCount' => 1, 'scream' => false),
                array('message' => 'foo2', 'context' => array('exception' => array('deprecated', E_USER_DEPRECATED)), 'priority' => 100, 'priorityName' => 'DEBUG', 'errorCount' => 1, 'scream' => false),
            ),
            2,
            0,
            array(100 => array('count' => 3, 'name' => 'DEBUG')),
        );

        yield 'logs with some silent errors' => array(
            1,
            array(
                array('message' => 'foo3', 'context' => array('exception' => new \ErrorException('warning', 0, E_USER_WARNING)), 'priority' => 100, 'priorityName' => 'DEBUG'),
                array('message' => 'foo3', 'context' => array('exception' => new SilencedErrorContext(E_USER_WARNING, __FILE__, __LINE__)), 'priority' => 100, 'priorityName' => 'DEBUG'),
            ),
            array(
                array('message' => 'foo3', 'context' => array('exception' => array('warning', E_USER_WARNING)), 'priority' => 100, 'priorityName' => 'DEBUG'),
                array('message' => 'foo3', 'context' => array('exception' => array(E_USER_WARNING)), 'priority' => 100, 'priorityName' => 'DEBUG', 'errorCount' => 1, 'scream' => true),
            ),
            0,
            1,
        );
    }
}
