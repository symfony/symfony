<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Messenger\Transport\Semaphore;

use Symfony\Component\Messenger\Exception\InvalidArgumentException;
use Symfony\Component\Messenger\Transport\Semaphore\Exception\SemaphoreException;

/**
 * A Semaphore connection.
 *
 * @author Cedrick Oka Baidai <okacedrick@gmail.com>
 */
class Connection
{
    private const DEFAULT_OPTIONS = [
        'path' => __FILE__,
        'project' => 'M',
        'message_max_size' => 131072,
        'auto_setup' => true,
    ];

    /**
     * @var array
     */
    private $configuration;

    /**
     * @var resource
     */
    private $queue;

    /**
     * @var bool
     */
    private $setup;

    public function __construct(array $configuration)
    {
        $this->configuration = array_replace_recursive(self::DEFAULT_OPTIONS, $configuration);
        $this->setup = false;

        $this->configuration['message_max_size'] = (int) $this->configuration['message_max_size'];

        if (true === isset($this->configuration['message_type'])) {
            $this->configuration['message_type'] = (int) $this->configuration['message_type'];
        }
    }

    /**
     * Creates a connection based on the DSN and options.
     *
     * Available options:
     *
     *   * path: Pathname to create System V IPC key (Default: __FILE__)
     *   * project: Project identifier to create System V IPC key (Default: M)
     *   * message_type: The type of message to send (Default: 1)
     *   * message_max_size: The maximum size of a message if the message is larger than this size, an exception will be thrown (Default: 131072)
     *   * auto_setup: Enable or not the auto-setup of queue (Default: true)
     */
    public static function fromDsn(string $dsn, array $options = []): self
    {
        // semaphore:///... => semaphore://localhost/... or else the URL will be invalid
        $dsn = preg_replace('#^(semaphore):///#', '$1://localhost/', $dsn);

        if (false === $parsedUrl = parse_url($dsn)) {
            throw new InvalidArgumentException(sprintf('The given Semaphore Messenger DSN "%s" is invalid.', $dsn));
        }

        $parsedQuery = [];
        parse_str($parsedUrl['query'] ?? '', $parsedQuery);

        $queueOptions = array_replace_recursive([
            'path' => $parsedUrl['path'] ?? self::DEFAULT_OPTIONS['path'],
        ], $options, $parsedQuery);

        return new self($queueOptions);
    }

    public function getConfiguration(): array
    {
        return $this->configuration;
    }

    /**
     * Send message on semaphore queue.
     * 
     * @param SemaphoreStamp $semaphoreStamp
     * 
     * @throws SemaphoreException
     */
    public function send(string $body, array $headers = [], int $delay = 0, SemaphoreStamp $semaphoreStamp = null): void
    {
        $messageType = null !== $semaphoreStamp ? $semaphoreStamp->getType() : ($this->configuration['message_type'] ?? 1);
        $message = json_encode(['body' => $body, 'headers' => $headers]);

        if ($this->configuration['message_max_size'] < \strlen($message)) {
            throw new SemaphoreException(sprintf('The semaphore message is too long to be sent, the maximum size accepted is 10 bytes.', $this->configuration['message_max_size']));
        }

        if (false === msg_send($this->getQueue(), $messageType, $message, false, false, $errorCode)) {
            throw new SemaphoreException(sprintf('Semaphore sending message failed with error code : "%s".', $errorCode));
        }
    }

    /**
     * Waits and gets a message from the configured semaphore queue.
     *
     * @throws SemaphoreException
     */
    public function get(): ?SemaphoreEnvelope
    {
        if (true === msg_receive($this->getQueue(), $this->configuration['message_type'] ?? 0, $messageType, $this->configuration['message_max_size'], $message, false, MSG_IPC_NOWAIT, $errorCode)) {
            $message = json_decode($message, true);

            return new SemaphoreEnvelope($messageType, $message['body'], $message['headers']);
        }

        if (MSG_ENOMSG !== $errorCode) {
            throw new SemaphoreException(sprintf('Semaphore receiving message failed with error code : "%s".', $errorCode));
        }

        return null;
    }

    public function getMessageCount(): int
    {
        $stat = msg_stat_queue($this->getQueue());

        return $stat['msg_qnum'] ?? 0;
    }

    public function close(): void
    {
        msg_remove_queue($this->getQueue());
    }

    public function setup(): void
    {
        $key = ftok($this->configuration['path'], substr($this->configuration['project'], 0, 1));
        $this->queue = msg_get_queue($key);
        $this->setup = true;
    }

    private function shouldSetup(): bool
    {
        if (true === $this->setup) {
            return false;
        }

        if (true === \in_array($this->configuration['auto_setup'], [false, 'false'], true)) {
            return false;
        }

        return true;
    }

    /**
     * @return resource
     */
    private function getQueue()
    {
        if (true === $this->shouldSetup()) {
            $this->setup();
        }

        return $this->queue;
    }
}
