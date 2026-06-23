<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\FrameworkBundle\Debug;

use Revolt\EventLoop;

/**
 * @internal
 */
final class DebugAsyncTask
{
    /** @var resource|null */
    private $socket;
    private string $buffer = '';
    private ?string $readWatcher = null;
    private bool $finished = false;

    /**
     * @param positive-int                       $pid
     * @param callable(mixed, string|null): void $onComplete
     */
    private function __construct(
        private readonly int $pid,
        mixed $socket,
        private readonly \Closure $onComplete,
    ) {
        $this->socket = $socket;
        stream_set_blocking($this->socket, false);

        $this->readWatcher = EventLoop::onReadable($this->socket, $this->read(...));
    }

    /**
     * @param callable(): mixed                  $work
     * @param callable(mixed, string|null): void $onComplete
     */
    public static function run(callable $work, callable $onComplete): ?self
    {
        if (!self::isSupported()) {
            try {
                $onComplete($work(), null);
            } catch (\Throwable $e) {
                $onComplete(null, $e->getMessage());
            }

            return null;
        }

        $sockets = stream_socket_pair(\STREAM_PF_UNIX, \STREAM_SOCK_STREAM, \STREAM_IPPROTO_IP);
        if (false === $sockets) {
            try {
                $onComplete($work(), null);
            } catch (\Throwable $e) {
                $onComplete(null, $e->getMessage());
            }

            return null;
        }

        $pid = pcntl_fork();
        if (-1 === $pid) {
            fclose($sockets[0]);
            fclose($sockets[1]);

            try {
                $onComplete($work(), null);
            } catch (\Throwable $e) {
                $onComplete(null, $e->getMessage());
            }

            return null;
        }

        if (0 === $pid) {
            fclose($sockets[0]);

            try {
                $payload = serialize(['result' => $work(), 'error' => null]);
            } catch (\Throwable $e) {
                $payload = serialize(['result' => null, 'error' => $e->getMessage()]);
            }

            fwrite($sockets[1], $payload);
            fclose($sockets[1]);
            exit(0);
        }

        fclose($sockets[1]);

        return new self($pid, $sockets[0], $onComplete(...));
    }

    public function cancel(): void
    {
        if ($this->finished) {
            return;
        }

        $this->finished = true;
        $this->close();

        if (\function_exists('posix_kill')) {
            @posix_kill($this->pid, \defined('SIGTERM') ? \SIGTERM : 15);
        }

        self::reap($this->pid);
    }

    private static function isSupported(): bool
    {
        return \function_exists('pcntl_fork') && \function_exists('pcntl_waitpid') && \function_exists('stream_socket_pair') && \defined('STREAM_PF_UNIX');
    }

    private static function reap(int $pid, int $attempt = 0): void
    {
        if (0 < pcntl_waitpid($pid, $status, \WNOHANG) || $attempt >= 10) {
            return;
        }

        EventLoop::delay(0.1, static function () use ($pid, $attempt): void {
            self::reap($pid, $attempt + 1);
        });
    }

    private function read(): void
    {
        if (null === $this->socket) {
            return;
        }

        $chunk = fread($this->socket, 8192);
        if (false !== $chunk && '' !== $chunk) {
            $this->buffer .= $chunk;
        }

        if (!feof($this->socket)) {
            return;
        }

        $this->finished = true;
        $this->close();
        self::reap($this->pid);

        $payload = '' === $this->buffer ? false : @unserialize($this->buffer, ['allowed_classes' => [DebugItem::class]]);
        if (!\is_array($payload) || !\array_key_exists('result', $payload) || !\array_key_exists('error', $payload)) {
            ($this->onComplete)(null, 'The debug task did not return a valid result.');

            return;
        }

        ($this->onComplete)($payload['result'], $payload['error']);
    }

    private function close(): void
    {
        if (null !== $this->readWatcher) {
            EventLoop::cancel($this->readWatcher);
            $this->readWatcher = null;
        }

        if (null !== $this->socket) {
            $socket = $this->socket;
            $this->socket = null;
            fclose($socket);
        }
    }
}
