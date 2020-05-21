<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Scheduler\Bridge\Nomad\Transport;

use Symfony\Component\HttpClient\ScopingHttpClient;
use Symfony\Component\Scheduler\Exception\InvalidArgumentException;
use Symfony\Component\Scheduler\Exception\TransportException;
use Symfony\Component\Scheduler\Task\TaskInterface;
use Symfony\Component\Scheduler\Task\TaskListInterface;
use Symfony\Component\Scheduler\Transport\ConnectionInterface;
use Symfony\Component\Scheduler\Transport\Dsn;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Throwable;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class Connection implements ConnectionInterface
{
    /**
     * {@see https://www.nomadproject.io/api-docs/jobs/#jobs-http-api}
     */
    private const BASE_URL = '/v1/jobs';

    /**
     * {@see https://www.nomadproject.io/api-docs/jobs/#stop-a-job}
     */
    private const DELETE_URL = '/v1/job/%s';

    private $aclToken;
    private $dsn;
    private $httpClient;
    private $serializer;
    private $options;

    public function __construct(Dsn $dsn, HttpClientInterface $httpClient, SerializerInterface $serializer, array $options = [])
    {
        $this->dsn = $dsn;
        $this->httpClient = $httpClient;
        $this->serializer = $serializer;
        $this->options = $options;

        if (null === $token = $dsn->getUser()) {
            throw new InvalidArgumentException('The "acl_token" must be provided.');
        }

        $this->aclToken = $token;
        $this->httpClient = $this->warmClient($httpClient, $dsn->getHost());
    }

    /**
     * {@inheritdoc}
     */
    public function create(TaskInterface $task): void
    {
        // TODO: Implement create() method.
    }

    /**
     * {@inheritdoc}
     */
    public function list(): TaskListInterface
    {
        try {
            $response = $this->httpClient->request('GET', sprintf(self::BASE_URL));


        } catch (Throwable $exception) {
            throw new TransportException($exception->getMessage());
        }
    }

    /**
     * {@inheritdoc}
     */
    public function get(string $taskName): TaskInterface
    {
        // TODO: Implement get() method.
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
                'headers' => [
                    'X-Nomad-Token' => $this->aclToken,
                ],
                'query' => [
                    'purge' => true,
                ],
            ]);
        } catch (Throwable $throwable) {
            throw new TransportException($throwable->getMessage());
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
