<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Tests\Component\HttpKernel\DataCollector;

use Symfony\Component\HttpKernel\DataCollector\LoggerDataCollector;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class LoggerDataCollectorTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @dataProvider getCollectTestData
     */
    public function testCollect($nb, $logs, $expected)
    {
        $logger = $this->getMock('Symfony\Component\HttpKernel\Log\DebugLoggerInterface');
        $logger->expects($this->once())->method('countErrors')->will($this->returnValue($nb));
        $logger->expects($this->once())->method('getLogs')->will($this->returnValue($logs));

        $c = new LoggerDataCollector($logger);
        $c->collect(new Request(), new Response());

        $this->assertSame('logger', $c->getName());
        $this->assertSame($nb, $c->countErrors());
        $this->assertSame($expected ? $expected : $logs, $c->getLogs());
    }

    public function getCollectTestData()
    {
        return array(
            array(
                1,
                array(array('message' => 'foo', 'context' => array())),
                null,
            ),
            array(
                1,
                array(array('message' => 'foo', 'context' => array('foo' => fopen(__FILE__, 'r')))),
                array(array('message' => 'foo', 'context' => array('foo' => 'Resource(stream)'))),
            ),
            array(
                1,
                array(array('message' => 'foo', 'context' => array('foo' => new \stdClass()))),
                array(array('message' => 'foo', 'context' => array('foo' => 'Object(stdClass)'))),
            ),
        );
    }
}
