<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Console\Helper;

use Symfony\Component\Console\Exception\RuntimeException;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\StreamableInputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * This class provides helpers to interact with the socket stream server.
 *
 * @author Louis-Marie Gaborit <lm.gabo@gmail.com>
 */
class StreamHelper extends Helper
{
    /**
     * @var resource|null
     */
    private $inputStream;

    /**
     * @var resource|null
     */
    private $socket;

    private static bool $stty = true;
    private static bool $stdinIsInteractive;

    private function start(string $host): void
    {
        if (!$this->socket = stream_socket_server($host, $errno, $errstr)) {
            throw new RuntimeException(sprintf('Server start failed on "%s": ', $host).$errstr.' '.$errno);
        }
    }

    public function listen(InputInterface $input, OutputInterface $output, string $host, callable $callback): void
    {
        if ($input instanceof StreamableInputInterface && $stream = $input->getStream()) {
            $this->inputStream = $stream;
        } elseif (null === $this->socket) {
            if (!str_contains($host, '://')) {
                $host = 'tcp://'.$host;
            }

            $this->start($host);
        }

        $io = new SymfonyStyle($input, $output);
        $errorIo = $io->getErrorStyle();

        $errorIo->success(sprintf('Server listening on %s', $host));
        $errorIo->comment('Quit the server with CONTROL-C.');

        foreach ($this->getMessages() as $clientId => $message) {
            $callback($clientId, $message);
        }
    }

    public function getName(): string
    {
        return 'stream';
    }

    private function getMessages(): iterable
    {
        if (null !== $inputStream = $this->inputStream) {
            while (!feof($inputStream)) {
                $stream = fgets($inputStream);
                yield (int) $stream => $stream;
            }
        } else {
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
}
