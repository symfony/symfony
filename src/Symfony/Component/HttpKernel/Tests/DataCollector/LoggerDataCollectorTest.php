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

use Symfony\Component\Debug\Exception\SilencedErrorContext;
use Symfony\Component\HttpKernel\DataCollector\LoggerDataCollector;

class LoggerDataCollectorTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @dataProvider getCollectTestData
     */
    public function testCollect($nb, $logs, $expectedLogs, $expectedDeprecationCount, $expectedScreamCount, $expectedPriorities = null)
    {
        $logger = $this->getMock('Symfony\Component\HttpKernel\Log\DebugLoggerInterface');
        $logger->expects($this->once())->method('countErrors')->will($this->returnValue($nb));
        $logger->expects($this->exactly(2))->method('getLogs')->will($this->returnValue($logs));

        $c = new LoggerDataCollector($logger);
        $c->lateCollect();

        // Remove the trace from the real logs, to ease fixtures creation.
        $logs = array_map(function ($log) {
            unset($log['context']['trace'], $log['context']['exception']['trace']);

            return $log;
        }, $c->getLogs());

        $this->assertEquals('logger', $c->getName());
        $this->assertEquals($nb, $c->countErrors());
        $this->assertEquals($expectedLogs, $logs);
        $this->assertEquals($expectedDeprecationCount, $c->countDeprecations());
        $this->assertEquals($expectedScreamCount, $c->countScreams());

        if (isset($expectedPriorities)) {
            $this->assertSame($expectedPriorities, $c->getPriorities());
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

        yield 'log with a resource' => array(
            1,
            array(array('message' => 'foo', 'context' => array('foo' => fopen(__FILE__, 'r')), 'priority' => 100, 'priorityName' => 'DEBUG')),
            array(array('message' => 'foo', 'context' => array('foo' => 'Resource(stream)'), 'priority' => 100, 'priorityName' => 'DEBUG')),
            0,
            0,
        );

        yield 'log with an object' => array(
            1,
            array(array('message' => 'foo', 'context' => array('foo' => new \stdClass()), 'priority' => 100, 'priorityName' => 'DEBUG')),
            array(array('message' => 'foo', 'context' => array('foo' => 'Object(stdClass)'), 'priority' => 100, 'priorityName' => 'DEBUG')),
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
                array('message' => 'foo3', 'context' => array('exception' => array('file' => __FILE__, 'line' => 82, 'class' => \ErrorException::class, 'message' => 'warning')), 'priority' => 100, 'priorityName' => 'DEBUG'),
                array('message' => 'foo', 'context' => array('type' => 'E_DEPRECATED', 'file' => __FILE__, 'line' => 83, 'errorCount' => 1, 'scream' => false), 'priority' => 100, 'priorityName' => 'DEBUG'),
                array('message' => 'foo2', 'context' => array('type' => 'E_USER_DEPRECATED', 'file' => __FILE__, 'line' => 84, 'errorCount' => 1, 'scream' => false), 'priority' => 100, 'priorityName' => 'DEBUG'),
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
                array('message' => 'foo3', 'context' => array('exception' => array('file' => __FILE__, 'line' => 99, 'class' => \ErrorException::class, 'message' => 'warning')), 'priority' => 100, 'priorityName' => 'DEBUG'),
                array('message' => 'foo3', 'context' => array('type' => 'E_USER_WARNING', 'file' => __FILE__, 'line' => 100, 'errorCount' => 1, 'scream' => true), 'priority' => 100, 'priorityName' => 'DEBUG'),
            ),
            0,
            1,
        );
    }
}
