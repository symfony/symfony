<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Messenger\Bridge\Beanstalkd\Transport;

use Pheanstalk\Contract\PheanstalkManagerInterface;
use Pheanstalk\Contract\PheanstalkPublisherInterface;
use Pheanstalk\Contract\PheanstalkSubscriberInterface;
use Pheanstalk\Contract\SocketFactoryInterface;
use Pheanstalk\Exception;
use Pheanstalk\Exception\ConnectionException;
use Pheanstalk\Pheanstalk;
use Pheanstalk\Values\JobId;
use Pheanstalk\Values\TubeName;
use Symfony\Component\Messenger\Exception\InvalidArgumentException;
use Symfony\Component\Messenger\Exception\TransportException;

/**
 * @author Antonio Pauletich <antonio.pauletich95@gmail.com>
 *
 * @internal
 *
 * @final
 */
class Connection
{
    private const DEFAULT_OPTIONS = [
        'tube_name' => 'default',
        'timeout' => 0,
        'ttr' => 90,
        'bury_on_reject' => false,
    ];

    private TubeName $tube;
    private int $timeout;
    private int $ttr;
    private bool $buryOnReject;

    private bool $usingTube = false;
    private bool $watchingTube = false;

    /**
     * Constructor.
     *
     * Available options:
     *
     * * tube_name: name of the tube
     * * timeout: message reservation timeout (in seconds)
     * * ttr: the message time to run before it is put back in the ready queue (in seconds)
     * * bury_on_reject: bury rejected messages instead of deleting them
     */
    public function __construct(
        private array $configuration,
        private PheanstalkSubscriberInterface&PheanstalkPublisherInterface&PheanstalkManagerInterface $client,
    ) {
        $this->configuration = array_replace_recursive(self::DEFAULT_OPTIONS, $configuration);
        $this->tube = new TubeName($this->configuration['tube_name']);
        $this->timeout = $this->configuration['timeout'];
        $this->ttr = $this->configuration['ttr'];
        $this->buryOnReject = $this->configuration['bury_on_reject'];
    }

    public static function fromDsn(#[\SensitiveParameter] string $dsn, array $options = []): self
    {
        if (false === $components = parse_url($dsn)) {
            throw new InvalidArgumentException('The given Beanstalkd DSN is invalid.');
        }

        $connectionCredentials = [
            'host' => $components['host'],
            'port' => $components['port'] ?? SocketFactoryInterface::DEFAULT_PORT,
        ];

        $query = [];
        if (isset($components['query'])) {
            parse_str($components['query'], $query);
        }

        $configuration = [];
        foreach (self::DEFAULT_OPTIONS as $k => $v) {
            $value = $options[$k] ?? $query[$k] ?? $v;

            $configuration[$k] = match (\gettype($v)) {
                'integer' => filter_var($value, \FILTER_VALIDATE_INT),
                'boolean' => filter_var($value, \FILTER_VALIDATE_BOOL),
                default => $value,
            };
        }

        // check for extra keys in options
        $optionsExtraKeys = array_diff(array_keys($options), array_keys(self::DEFAULT_OPTIONS));
        if (0 < \count($optionsExtraKeys)) {
            throw new InvalidArgumentException(\sprintf('Unknown option found : [%s]. Allowed options are [%s].', implode(', ', $optionsExtraKeys), implode(', ', array_keys(self::DEFAULT_OPTIONS))));
        }

        // check for extra keys in options
        $queryExtraKeys = array_diff(array_keys($query), array_keys(self::DEFAULT_OPTIONS));
        if (0 < \count($queryExtraKeys)) {
            throw new InvalidArgumentException(\sprintf('Unknown option found in DSN: [%s]. Allowed options are [%s].', implode(', ', $queryExtraKeys), implode(', ', array_keys(self::DEFAULT_OPTIONS))));
        }

        return new self(
            $configuration,
            Pheanstalk::create($connectionCredentials['host'], $connectionCredentials['port'])
        );
    }

    public function getConfiguration(): array
    {
        return $this->configuration;
    }

    public function getTube(): string
    {
        return (string) $this->tube;
    }

    /**
     * @param int  $delay    The delay in milliseconds
     * @param ?int $priority The priority at which the message will be reserved
     *
     * @return string The inserted id
     */
    public function send(string $body, array $headers, int $delay = 0, ?int $priority = null): string
    {
        try {
            $message = json_encode([
                'body' => $body,
                'headers' => $headers,
            ], \JSON_THROW_ON_ERROR);
        } catch (\JsonException $exception) {
            throw new TransportException($exception->getMessage(), 0, $exception);
        }

        return $this->withReconnect(function () use ($message, $delay, $priority) {
            $this->useTube();
            $job = $this->client->put(
                $message,
                $priority ?? PheanstalkPublisherInterface::DEFAULT_PRIORITY,
                (int) ($delay / 1000),
                $this->ttr
            );

            return $job->getId();
        });
    }

    public function get(): ?array
    {
        $job = $this->withReconnect(function () {
            $this->watchTube();

            return $this->client->reserveWithTimeout($this->timeout);
        });

        if (null === $job) {
            return null;
        }

        $data = $job->getData();

        try {
            $beanstalkdEnvelope = json_decode($data, true, flags: \JSON_THROW_ON_ERROR);
        } catch (\JsonException $exception) {
            throw new TransportException($exception->getMessage(), 0, $exception);
        }

        return [
            'id' => $job->getId(),
            'body' => $beanstalkdEnvelope['body'],
            'headers' => $beanstalkdEnvelope['headers'],
        ];
    }

    public function ack(string $id): void
    {
        $this->withReconnect(function () use ($id) {
            $this->useTube();
            $this->client->delete(new JobId($id));
        });
    }

    public function reject(string $id, ?int $priority = null, bool $forceDelete = false): void
    {
        $this->withReconnect(function () use ($id, $priority, $forceDelete) {
            $this->useTube();

            if (!$forceDelete && $this->buryOnReject) {
                $this->client->bury(new JobId($id), $priority ?? PheanstalkPublisherInterface::DEFAULT_PRIORITY);
            } else {
                $this->client->delete(new JobId($id));
            }
        });
    }

    public function keepalive(string $id): void
    {
        $this->withReconnect(function () use ($id) {
            $this->useTube();
            $this->client->touch(new JobId($id));
        });
    }

    public function getMessageCount(): int
    {
        return $this->withReconnect(function () {
            $this->useTube();
            $tubeStats = $this->client->statsTube($this->tube);

            return $tubeStats->currentJobsReady;
        });
    }

    public function getMessagePriority(string $id): int
    {
        return $this->withReconnect(function () use ($id) {
            $jobStats = $this->client->statsJob(new JobId($id));

            return $jobStats->priority;
        });
    }

    private function useTube(): void
    {
        if ($this->usingTube) {
            return;
        }

        $this->client->useTube($this->tube);
        $this->usingTube = true;
    }

    private function watchTube(): void
    {
        if ($this->watchingTube) {
            return;
        }

        if ($this->client->watch($this->tube) > 1) {
            foreach ($this->client->listTubesWatched() as $tube) {
                if ((string) $tube !== (string) $this->tube) {
                    $this->client->ignore($tube);
                }
            }
        }

        $this->watchingTube = true;
    }

    private function withReconnect(callable $command): mixed
    {
        try {
            try {
                return $command();
            } catch (ConnectionException) {
                $this->client->disconnect();

                $this->usingTube = false;
                $this->watchingTube = false;

                return $command();
            }
        } catch (Exception $exception) {
            throw new TransportException($exception->getMessage(), 0, $exception);
        }
    }
}
