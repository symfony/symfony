<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bridge\Monolog\Tests\Processor;

use Monolog\Logger;
use Symfony\Bridge\Monolog\Processor\WebProcessor;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\HttpKernelInterface;

class WebProcessorTest extends \PHPUnit_Framework_TestCase
{
    protected function setUp()
    {
        if (!class_exists('Monolog\\Logger')) {
            $this->markTestSkipped('Monolog is not available.');
        }
    }

    public function testUsesRequestServerData()
    {
        $server = array(
            'REQUEST_URI'    => 'A',
            'REMOTE_ADDR'    => 'B',
            'REQUEST_METHOD' => 'C',
            'SERVER_NAME'    => 'D',
            'HTTP_REFERER'   => 'E',
        );

        $request = new Request();
        $request->server->replace($server);

        $event = $this->getMockBuilder('Symfony\Component\HttpKernel\Event\GetResponseEvent')
            ->disableOriginalConstructor()
            ->getMock();
        $event->expects($this->any())
            ->method('getRequestType')
            ->will($this->returnValue(HttpKernelInterface::MASTER_REQUEST));
        $event->expects($this->any())
            ->method('getRequest')
            ->will($this->returnValue($request));

        $processor = new WebProcessor();
        $processor->onKernelRequest($event);
        $record = $processor($this->getRecord());

        $this->assertEquals($server['REQUEST_URI'], $record['extra']['url']);
        $this->assertEquals($server['REMOTE_ADDR'], $record['extra']['ip']);
        $this->assertEquals($server['REQUEST_METHOD'], $record['extra']['http_method']);
        $this->assertEquals($server['SERVER_NAME'], $record['extra']['server']);
        $this->assertEquals($server['HTTP_REFERER'], $record['extra']['referrer']);
    }

    /**
     * @param int     $level
     * @param string  $message
     *
     * @return array Record
     */
    protected function getRecord($level = Logger::WARNING, $message = 'test')
    {
        return array(
            'message' => $message,
            'context' => array(),
            'level' => $level,
            'level_name' => Logger::getLevelName($level),
            'channel' => 'test',
            'datetime' => new \DateTime(),
            'extra' => array(),
        );
    }
}
