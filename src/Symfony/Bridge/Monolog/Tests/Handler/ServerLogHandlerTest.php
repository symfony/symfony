<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bridge\Monolog\Tests\Handler;

use Monolog\Formatter\JsonFormatter;
use Monolog\Logger;
use Monolog\Processor\ProcessIdProcessor;
use PHPUnit\Framework\TestCase;
use Symfony\Bridge\Monolog\Formatter\VarDumperFormatter;
use Symfony\Bridge\Monolog\Handler\ServerLogHandler;
use Symfony\Bridge\Monolog\Tests\RecordFactory;
use Symfony\Component\VarDumper\Cloner\Data;

/**
 * Tests the ServerLogHandler.
 */
class ServerLogHandlerTest extends TestCase
{
    public function testFormatter()
    {
        $handler = new ServerLogHandler('tcp://127.0.0.1:9999');
        $this->assertInstanceOf(VarDumperFormatter::class, $handler->getFormatter());

        $formatter = new JsonFormatter();
        $handler->setFormatter($formatter);
        $this->assertSame($formatter, $handler->getFormatter());
    }

    public function testIsHandling()
    {
        $handler = new ServerLogHandler('tcp://127.0.0.1:9999', Logger::INFO);
        $this->assertFalse($handler->isHandling(RecordFactory::create(Logger::DEBUG)), '->isHandling returns false when no output is set');
    }

    public function testGetFormatter()
    {
        $handler = new ServerLogHandler('tcp://127.0.0.1:9999');
        $this->assertInstanceOf(VarDumperFormatter::class, $handler->getFormatter(),
            '->getFormatter returns VarDumperFormatter by default'
        );
    }

    public function testWritingAndFormatting()
    {
        $host = 'tcp://127.0.0.1:9999';
        $handler = new ServerLogHandler($host, Logger::INFO, false);
        $handler->pushProcessor(new ProcessIdProcessor());

        $infoRecord = RecordFactory::create(Logger::INFO, 'My info message', 'app', datetime: new \DateTimeImmutable('2013-05-29 16:21:54'));

        $socket = stream_socket_server($host, $errno, $errstr);
        $this->assertIsResource($socket, sprintf('Server start failed on "%s": %s %s.', $host, $errstr, $errno));

        $this->assertTrue($handler->handle($infoRecord), 'The handler finished handling the log as bubble is false.');

        $sockets = [(int) $socket => $socket];
        $write = [];

        for ($i = 0; $i < 10; ++$i) {
            $read = $sockets;
            stream_select($read, $write, $write, null);

            foreach ($read as $stream) {
                if ($socket === $stream) {
                    $stream = stream_socket_accept($socket);
                    $sockets[(int) $stream] = $stream;
                } elseif (feof($stream)) {
                    unset($sockets[(int) $stream]);
                    fclose($stream);
                } else {
                    $message = fgets($stream);
                    fclose($stream);

                    $record = unserialize(base64_decode($message));
                    $this->assertIsArray($record);

                    $this->assertArrayHasKey('message', $record);
                    $this->assertEquals('My info message', $record['message']);

                    $this->assertArrayHasKey('extra', $record);
                    $this->assertInstanceOf(Data::class, $record['extra']);
                    $extra = $record['extra']->getValue(true);
                    $this->assertIsArray($extra);
                    $this->assertArrayHasKey('process_id', $extra);
                    $this->assertSame(getmypid(), $extra['process_id']);

                    return;
                }
            }
            usleep(100000);
        }
        $this->fail('Fail to read message from server');
    }
}
