<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bridge\Monolog\Handler;

use Monolog\Handler\AbstractHandler;
use Monolog\Logger;
use Symfony\Bridge\Monolog\Formatter\VarDumperFormatter;

/**
 * @author Grégoire Pineau <lyrixx@lyrixx.info>
 */
class ServerLogHandler extends AbstractHandler
{
    private $host;
    private $context;
    private $socket;

    public function __construct($host, $level = Logger::DEBUG, $bubble = true, $context = [])
    {
        parent::__construct($level, $bubble);

        if (false === strpos($host, '://')) {
            $host = 'tcp://'.$host;
        }

        $this->host = $host;
        $this->context = stream_context_create($context);
    }

    /**
     * {@inheritdoc}
     */
    public function handle(array $record)
    {
        if (!$this->isHandling($record)) {
            return false;
        }

        set_error_handler(self::class.'::nullErrorHandler');

        try {
            if (!$this->socket = $this->socket ?: $this->createSocket()) {
                return false === $this->bubble;
            }
        } finally {
            restore_error_handler();
        }

        $recordFormatted = $this->formatRecord($record);

        set_error_handler(self::class.'::nullErrorHandler');

        try {
            if (-1 === stream_socket_sendto($this->socket, $recordFormatted)) {
                stream_socket_shutdown($this->socket, \STREAM_SHUT_RDWR);

                // Let's retry: the persistent connection might just be stale
                if ($this->socket = $this->createSocket()) {
                    stream_socket_sendto($this->socket, $recordFormatted);
                }
            }
        } finally {
            restore_error_handler();
        }

        return false === $this->bubble;
    }

    /**
     * {@inheritdoc}
     */
    protected function getDefaultFormatter()
    {
        return new VarDumperFormatter();
    }

    private static function nullErrorHandler()
    {
    }

    private function createSocket()
    {
        $socket = stream_socket_client($this->host, $errno, $errstr, 0, \STREAM_CLIENT_CONNECT | \STREAM_CLIENT_ASYNC_CONNECT | \STREAM_CLIENT_PERSISTENT, $this->context);

        if ($socket) {
            stream_set_blocking($socket, false);
        }

        return $socket;
    }

    private function formatRecord(array $record)
    {
        if ($this->processors) {
            foreach ($this->processors as $processor) {
                $record = \call_user_func($processor, $record);
            }
        }

        $recordFormatted = $this->getFormatter()->format($record);

        foreach (['log_uuid', 'uuid', 'uid'] as $key) {
            if (isset($record['extra'][$key])) {
                $recordFormatted['log_id'] = $record['extra'][$key];
                break;
            }
        }

        return base64_encode(serialize($recordFormatted))."\n";
    }
}
