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
use PHPUnit\Framework\TestCase;
use Symfony\Bridge\Monolog\Processor\WebProcessor;
use Symfony\Component\HttpFoundation\Request;

class WebProcessorTest extends TestCase
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

    public function testUseRequestClientIp()
    {
        Request::setTrustedProxies(['192.168.0.1'], Request::HEADER_X_FORWARDED_ALL);
        list($event, $server) = $this->createRequestEvent(['X_FORWARDED_FOR' => '192.168.0.2']);

        $processor = new WebProcessor();
        $processor->onKernelRequest($event);
        $record = $processor($this->getRecord());

        $this->assertCount(5, $record['extra']);
        $this->assertEquals($server['REQUEST_URI'], $record['extra']['url']);
        $this->assertEquals($server['X_FORWARDED_FOR'], $record['extra']['ip']);
        $this->assertEquals($server['REQUEST_METHOD'], $record['extra']['http_method']);
        $this->assertEquals($server['SERVER_NAME'], $record['extra']['server']);
        $this->assertEquals($server['HTTP_REFERER'], $record['extra']['referrer']);

        Request::setTrustedProxies([], -1);
    }

    public function testCanBeConstructedWithExtraFields()
    {
        if (!$this->isExtraFieldsSupported()) {
            $this->markTestSkipped('WebProcessor of the installed Monolog version does not support $extraFields parameter');
        }

        list($event, $server) = $this->createRequestEvent();

        $processor = new WebProcessor(['url', 'referrer']);
        $processor->onKernelRequest($event);
        $record = $processor($this->getRecord());

        $this->assertCount(2, $record['extra']);
        $this->assertEquals($server['REQUEST_URI'], $record['extra']['url']);
        $this->assertEquals($server['HTTP_REFERER'], $record['extra']['referrer']);
    }

    /**
     * @return array
     */
    private function createRequestEvent($additionalServerParameters = [])
    {
        $server = array_merge(
            [
                'REQUEST_URI' => 'A',
                'REMOTE_ADDR' => '192.168.0.1',
                'REQUEST_METHOD' => 'C',
                'SERVER_NAME' => 'D',
                'HTTP_REFERER' => 'E',
            ],
            $additionalServerParameters
        );

        $request = new Request();
        $request->server->replace($server);
        $request->headers->replace($server);

        $event = $this->getMockBuilder('Symfony\Component\HttpKernel\Event\GetResponseEvent')
            ->disableOriginalConstructor()
            ->getMock();
        $event->expects($this->any())
            ->method('isMasterRequest')
            ->willReturn(true);
        $event->expects($this->any())
            ->method('getRequest')
            ->willReturn($request);

        return [$event, $server];
    }

    /**
     * @param int    $level
     * @param string $message
     *
     * @return array Record
     */
    private function getRecord($level = Logger::WARNING, $message = 'test')
    {
        return [
            'message' => $message,
            'context' => [],
            'level' => $level,
            'level_name' => Logger::getLevelName($level),
            'channel' => 'test',
            'datetime' => new \DateTime(),
            'extra' => [],
        ];
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
