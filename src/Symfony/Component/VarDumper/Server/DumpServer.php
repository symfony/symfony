<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\VarDumper\Server;

use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Input\StreamableInputInterface;
use Symfony\Component\VarDumper\Cloner\Data;
use Symfony\Component\VarDumper\Cloner\Stub;

/**
 * A server collecting Data clones sent by a ServerDumper.
 *
 * @author Maxime Steinhausser <maxime.steinhausser@gmail.com>
 *
 * @final
 */
class DumpServer
{
    private string $host;
    private ?LoggerInterface $logger;

    /**
     * @var resource|null
     */
    private $socket;

    public function __construct(string $host, ?LoggerInterface $logger = null)
    {
        if (!str_contains($host, '://')) {
            $host = 'tcp://'.$host;
        }

        $this->host = $host;
        $this->logger = $logger;
    }

    public function start(): void
    {
        if (!$this->socket = stream_socket_server($this->host, $errno, $errstr)) {
            throw new \RuntimeException(sprintf('Server start failed on "%s": ', $this->host).$errstr.' '.$errno);
        }
    }

    public function listen(callable $callback, ?StreamableInputInterface $streamInput = null): void
    {
        $inputStream = null;

        if ($streamInput instanceof StreamableInputInterface && $stream = $streamInput->getStream()) {
            $inputStream = $stream;
        } elseif (null === $this->socket) {
            $this->start();
        }

        foreach ($this->getMessages($inputStream) as $clientId => $message) {
            $this->logger?->info('Received a payload from client {clientId}', ['clientId' => $clientId]);

            $callback($clientId, $message);
        }
    }

    public function getHost(): string
    {
        return $this->host;
    }

    /**
     * @param ?resource
     */
    private function getMessages($inputStream): iterable
    {
        if (null !== $inputStream) {
            while (!feof($inputStream)) {
                $stream = fgets($inputStream);
                yield (int) $stream => $stream;
            }

            return;
        }
        
        $sockets = [(int) $this->socket => $this->socket];
        $write = [];

        while (true) {
            $read = $sockets;
            stream_select($read, $write, $write, null);

            foreach ($read as $stream) {
                if ($this->socket === $stream) {
                    $stream = stream_socket_accept($this->socket);
                    $sockets[(int) $stream] = $stream;
                } elseif (feof($stream)) {
                    unset($sockets[(int) $stream]);
                    fclose($stream);
                } else {
                    yield (int) $stream => fgets($stream);
                }
            }
        }
    }
}
