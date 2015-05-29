<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\DataCollecto\Tests;

use Symfony\Component\Profiler\DataCollector\LoggerDataCollector;

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
        $data = $c->lateCollect();

        $this->assertSame('logger', $data->getName());
        $this->assertSame($nb, $data->countErrors());
        $this->assertSame($expectedLogs ?: $logs, $data->getLogs());
        $this->assertSame($expectedDeprecationCount, $data->countDeprecations());
        $this->assertSame($expectedScreamCount, $data->countScreams());

        if (isset($expectedPriorities)) {
            $this->assertSame($expectedPriorities, $data->getPriorities());
        }
    }

    public function getCollectTestData()
    {
        return array(
            array(
                1,
                array(array('message' => 'foo', 'context' => array(), 'priority' => 100, 'priorityName' => 'DEBUG')),
                null,
                0,
                0,
            ),
            array(
                1,
                array(array('message' => 'foo', 'context' => array('foo' => fopen(__FILE__, 'r')), 'priority' => 100, 'priorityName' => 'DEBUG')),
                array(array('message' => 'foo', 'context' => array('foo' => 'Resource(stream)'), 'priority' => 100, 'priorityName' => 'DEBUG')),
                0,
                0,
            ),
            array(
                1,
                array(array('message' => 'foo', 'context' => array('foo' => new \stdClass()), 'priority' => 100, 'priorityName' => 'DEBUG')),
                array(array('message' => 'foo', 'context' => array('foo' => 'Object(stdClass)'), 'priority' => 100, 'priorityName' => 'DEBUG')),
                0,
                0,
            ),
            array(
                1,
                array(
                    array('message' => 'foo', 'context' => array('type' => E_DEPRECATED, 'level' => E_ALL), 'priority' => 100, 'priorityName' => 'DEBUG'),
                    array('message' => 'foo2', 'context' => array('type' => E_USER_DEPRECATED, 'level' => E_ALL), 'priority' => 100, 'priorityName' => 'DEBUG'),
                ),
                null,
                2,
                0,
                array(100 => array('count' => 2, 'name' => 'DEBUG')),
            ),
            array(
                1,
                array(array('message' => 'foo3', 'context' => array('type' => E_USER_WARNING, 'level' => 0), 'priority' => 100, 'priorityName' => 'DEBUG')),
                array(array('message' => 'foo3', 'context' => array('type' => E_USER_WARNING, 'level' => 0, 'scream' => true), 'priority' => 100, 'priorityName' => 'DEBUG')),
                0,
                1,
            ),
        );
    }
}
