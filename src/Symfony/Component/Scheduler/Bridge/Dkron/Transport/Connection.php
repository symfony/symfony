<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Scheduler\Bridge\Dkron\Transport;

use Symfony\Component\HttpClient\HttpClient;
use Symfony\Component\HttpClient\ScopingHttpClient;
use Symfony\Component\Scheduler\Bridge\Dkron\Task\Job;
use Symfony\Component\Scheduler\Exception\TransportException;
use Symfony\Component\Scheduler\Task\TaskInterface;
use Symfony\Component\Scheduler\Task\TaskListInterface;
use Symfony\Component\Scheduler\Transport\ConnectionInterface;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class Connection implements ConnectionInterface
{
    private const CONFIGURATION = [
        'host' => '127.0.0.1',
        'port' => 8080,
    ];

    /**
     * {@see https://dkron.io/api/#/jobs/createOrUpdateJob}
     */
    private const CREATE_URL = '/jobs';

    /**
     * {@see https://dkron.io/api/#/jobs/showJobByName}
     */
    private const GET_URL = '/jobs/%s';

    /**
     * {@see https://dkron.io/api/#/jobs/deleteJob}
     */
    private const DELETE_URL = '/jobs/%s';

    private $url;
    private $httpClient;
    private $serializer;

    public function __construct(array $options, SerializerInterface $serializer, HttpClientInterface $httpClient = null)
    {
        $this->url = $options['host'] ?? self::CONFIGURATION['host'];
        $this->serializer = $serializer;
        $this->httpClient = $httpClient ?? $this->warmClient(HttpClient::create(), $this->url);
    }

    /**
     * {@inheritdoc}
     */
    public function create(TaskInterface $task): void
    {
        try {
            $this->httpClient->request('POST', sprintf(self::CREATE_URL), [
                'headers' => [
                    'Content-Type' => 'application/json',
                ],
                'body' => $this->serializer->serialize($task, 'json'),
            ]);
        } catch (\Throwable $exception) {
            throw new TransportException($exception->getMessage());
        }
    }

    /**
     * {@inheritdoc}
     */
    public function list(): TaskListInterface
    {
        // TODO: Implement list() method.
    }

    /**
     * {@inheritdoc}
     */
    public function get(string $taskName): TaskInterface
    {
        try {
            $response = $this->httpClient->request('GET', sprintf(self::GET_URL, $taskName), [
                'Accept' => 'application/json',
            ]);

            return $this->serializer->deserialize($response, Job::class, 'json');
        } catch (\Throwable $exception) {
            throw new TransportException($exception->getMessage());
        }
    }

    /**
     * {@inheritdoc}
     */
    public function update(string $taskName, TaskInterface $updatedTask): void
    {
        // TODO: Implement update() method.
    }

    /**
     * {@inheritdoc}
     */
    public function pause(string $taskName): void
    {
        // TODO: Implement pause() method.
    }

    /**
     * {@inheritdoc}
     */
    public function resume(string $taskName): void
    {
        // TODO: Implement resume() method.
    }

    /**
     * {@inheritdoc}
     */
    public function delete(string $taskName): void
    {
        try {
            $this->httpClient->request('DELETE', sprintf(self::DELETE_URL, $taskName), [
                'Accept' => 'application/json',
            ]);
        } catch (\Throwable $exception) {
            throw new TransportException($exception->getMessage());
        }
    }

    /**
     * {@inheritdoc}
     */
    public function empty(): void
    {
        // TODO: Implement empty() method.
    }

    private function warmClient(HttpClientInterface $httpClient, string $host): HttpClientInterface
    {
        return ScopingHttpClient::forBaseUri($httpClient, sprintf('https://%s', $host), [
            'headers' => [
                'Accept' => 'application/json',
            ],
        ]);
    }
}
