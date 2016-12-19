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
use Symfony\Component\VarDumper\Cloner\Data;

class LoggerDataCollectorTest extends \PHPUnit_Framework_TestCase
{
    private static $data;

    /**
     * @dataProvider getCollectTestData
     */
    public function testCollect($nb, $logs, $expectedLogs, $expectedDeprecationCount, $expectedScreamCount, $expectedPriorities = null)
    {
        $logger = $this->getMockBuilder('Symfony\Component\HttpKernel\Log\DebugLoggerInterface')->getMock();
        $logger->expects($this->once())->method('countErrors')->will($this->returnValue($nb));
        $logger->expects($this->exactly(2))->method('getLogs')->will($this->returnValue($logs));

        // disable cloning the context, to ease fixtures creation.
        $c = $this->getMockBuilder(LoggerDataCollector::class)
            ->setMethods(array('cloneVar'))
            ->setConstructorArgs(array($logger))
            ->getMock();
        $c->expects($this->any())->method('cloneVar')->willReturn(self::$data);
        $c->lateCollect();

        $this->assertEquals('logger', $c->getName());
        $this->assertEquals($nb, $c->countErrors());
        $this->assertEquals($expectedLogs, $c->getLogs());
        $this->assertEquals($expectedDeprecationCount, $c->countDeprecations());
        $this->assertEquals($expectedScreamCount, $c->countScreams());

        if (isset($expectedPriorities)) {
            $this->assertSame($expectedPriorities, $c->getPriorities());
        }
    }

    public function getCollectTestData()
    {
        if (null === self::$data) {
            self::$data = new Data(array());
        }

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
            array(array('message' => 'foo', 'context' => self::$data, 'priority' => 100, 'priorityName' => 'DEBUG')),
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
                array('message' => 'foo3', 'context' => self::$data, 'priority' => 100, 'priorityName' => 'DEBUG'),
                array('message' => 'foo', 'context' => self::$data, 'priority' => 100, 'priorityName' => 'DEBUG', 'errorCount' => 1, 'scream' => false),
                array('message' => 'foo2', 'context' => self::$data, 'priority' => 100, 'priorityName' => 'DEBUG', 'errorCount' => 1, 'scream' => false),
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
                array('message' => 'foo3', 'context' => self::$data, 'priority' => 100, 'priorityName' => 'DEBUG'),
                array('message' => 'foo3', 'context' => self::$data, 'priority' => 100, 'priorityName' => 'DEBUG', 'errorCount' => 1, 'scream' => true),
            ),
            0,
            1,
        );
    }
}
