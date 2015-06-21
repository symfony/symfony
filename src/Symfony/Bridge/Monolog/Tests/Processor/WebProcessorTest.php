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
    public function testUsesRequestServerData()
    {
        list($event, $server) = $this->createRequestEvent();

        $processor = new WebProcessor();
        $processor->onKernelRequest($event);
        $record = $processor($this->getRecord());

        $this->assertCount(5, $record['extra']);
        $this->assertEquals($server['REQUEST_URI'], $record['extra']['url']);
        $this->assertEquals($server['REMOTE_ADDR'], $record['extra']['ip']);
        $this->assertEquals($server['REQUEST_METHOD'], $record['extra']['http_method']);
        $this->assertEquals($server['SERVER_NAME'], $record['extra']['server']);
        $this->assertEquals($server['HTTP_REFERER'], $record['extra']['referrer']);
    }

    public function testCanBeConstructedWithExtraFields()
    {
        if (!$this->isExtraFieldsSupported()) {
            $this->markTestSkipped('WebProcessor of the installed Monolog version does not support $extraFields parameter');
        }

        list($event, $server) = $this->createRequestEvent();

        $processor = new WebProcessor(array('url', 'referrer'));
        $processor->onKernelRequest($event);
        $record = $processor($this->getRecord());

        $this->assertCount(2, $record['extra']);
        $this->assertEquals($server['REQUEST_URI'], $record['extra']['url']);
        $this->assertEquals($server['HTTP_REFERER'], $record['extra']['referrer']);
    }

    /**
     * @return array
     */
    private function createRequestEvent()
    {
        $server = array(
            'REQUEST_URI' => 'A',
            'REMOTE_ADDR' => 'B',
            'REQUEST_METHOD' => 'C',
            'SERVER_NAME' => 'D',
            'HTTP_REFERER' => 'E',
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

        return array($event, $server);
    }

    /**
     * @param int    $level
     * @param string $message
     *
     * @return array Record
     */
    private function getRecord($level = Logger::WARNING, $message = 'test')
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

    private function isExtraFieldsSupported()
    {
        $monologWebProcessorClass = new \ReflectionClass('Monolog\Processor\WebProcessor');

        foreach ($monologWebProcessorClass->getConstructor()->getParameters() as $parameter) {
            if ('extraFields' === $parameter->getName()) {
                return true;
            }
        }

        return false;
    }
}
