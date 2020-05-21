<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Scheduler\Bridge\Kubernetes\Transport;

use Symfony\Component\HttpClient\HttpClient;
use Symfony\Component\HttpClient\ScopingHttpClient;
use Symfony\Component\Scheduler\Bridge\Kubernetes\Exception\InvalidOperationException;
use Symfony\Component\Scheduler\Bridge\Kubernetes\Task\CronJob;
use Symfony\Component\Scheduler\Exception\InvalidArgumentException;
use Symfony\Component\Scheduler\Task\TaskInterface;
use Symfony\Component\Scheduler\Task\TaskList;
use Symfony\Component\Scheduler\Task\TaskListInterface;
use Symfony\Component\Scheduler\Transport\ConnectionInterface;
use Symfony\Component\Scheduler\Transport\Dsn;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class Connection implements ConnectionInterface
{
    /**
     * https://kubernetes.io/docs/reference/generated/kubernetes-api/%apiVersion%/#create-cronjob-v1beta1-batch
     */
    private const CREATE_URL = '/apis/batch/v1beta1/namespaces/%s/cronjobs';

    /**
     * https://kubernetes.io/docs/reference/generated/kubernetes-api/%apiVersion%/#replace-cronjob-v1beta1-batch
     */
    private const REPLACE_URL = '/apis/batch/v1beta1/namespaces/%s/cronjobs/%s';

    /**
     * https://kubernetes.io/docs/reference/generated/kubernetes-api/%apiVersion%/#delete-cronjob-v1beta1-batch
     */
    private const DELETE_URL = '/apis/batch/v1beta1/namespaces/%s/cronjobs/%s';

    /**
     * https://kubernetes.io/docs/reference/generated/kubernetes-api/%apiVersion%/#delete-collection-cronjob-v1beta1-batch
     */
    private const DELETE_COLLECTION_URL = '/apis/batch/v1beta1/namespaces/%s/cronjobs';

    /**
     * https://kubernetes.io/docs/reference/generated/kubernetes-api/%apiVersion%/#read-cronjob-v1beta1-batch
     */
    private const GET_URL = '/apis/batch/v1beta1/namespaces/%s/cronjobs/%s';

    /**
     * https://kubernetes.io/docs/reference/generated/kubernetes-api/%apiVersion%/#list-cronjob-v1beta1-batch
     */
    private const LIST_URL = '/apis/batch/v1beta1/namespaces/%s/cronjobs';

    private $apiUrl;
    private $authenticationToken;
    private $httpClient;
    private $namespace;
    private $scheme;
    private $serializer;

    public function __construct(Dsn $dsn, SerializerInterface $serializer, HttpClientInterface $httpClient = null)
    {
        $this->apiUrl = $dsn->getHost();
        $this->namespace = $dsn->getOption('namespace');
        $this->scheme = $dsn->getOption('scheme') ?? 'https';

        if (null === $this->authenticationToken = $dsn->getUser()) {
            throw new InvalidArgumentException('The authentication token MUST be provided.');
        }

        $this->serializer = $serializer;
        $this->httpClient = $this->warmClient($httpClient ?? HttpClient::create());
    }

    /**
     * {@inheritdoc}
     */
    public function create(TaskInterface $task): void
    {
        try {
            $this->httpClient->request('POST', sprintf(self::CREATE_URL, $this->namespace), [
                'headers' => [
                    'Content-Type' => 'application/json',
                ],
                'auth_bearer' => $this->authenticationToken,
                'body' => $this->serializer->serialize($task, 'json'),
            ]);
        } catch (\Throwable $exception) {
            throw new InvalidOperationException('The task cannot be created: "%s"', $exception->getMessage());
        }
    }

    /**
     * {@inheritdoc}
     */
    public function list(): TaskListInterface
    {
        try {
            $response = $this->httpClient->request('GET', sprintf(self::LIST_URL, $this->namespace), [
                'headers' => [
                    'Accept' => 'application/json',
                ],
                'auth_bearer' => $this->authenticationToken,
            ]);

            if (!\array_key_exists('items', $response->toArray())) {
                throw new InvalidOperationException('The task list cannot be retrieved.');
            }

            $list = new TaskList();

            $task = $this->serializer->deserialize($response, 'Symfony\Component\Scheduler\Bridge\Kubernetes\Task\CronJob[]', 'json');
            $list->add($task);

            return $list;
        } catch (\Throwable $exception) {
            throw new InvalidOperationException('The task list cannot be retrieved: "%s"', $exception->getMessage());
        }
    }

    /**
     * {@inheritdoc}
     */
    public function get(string $taskName): TaskInterface
    {
        try {
            $response = $this->httpClient->request('GET', sprintf(self::GET_URL, $this->namespace, $taskName), [
                'headers' => [
                    'Accept' => 'application/json',
                ],
                'auth_bearer' => $this->authenticationToken,
            ]);

            return $this->serializer->deserialize($response, CronJob::class, 'json');
        } catch (\Throwable $exception) {
            throw new InvalidOperationException('The task cannot be updated: "%s"', $exception->getMessage());
        }
    }

    /**
     * {@inheritdoc}
     */
    public function update(string $taskName, TaskInterface $updatedTask): void
    {
        try {
            $this->httpClient->request('PUT', sprintf(self::REPLACE_URL, $this->namespace, $taskName), [
                'headers' => [
                    'Content-Type' => 'application/json',
                ],
                'auth_bearer' => $this->authenticationToken,
                'body' => $this->serializer->serialize($updatedTask, 'json'),
            ]);
        } catch (\Throwable $exception) {
            throw new InvalidOperationException('The task cannot be updated: "%s"', $exception->getMessage());
        }
    }

    /**
     * {@inheritdoc}
     */
    public function pause(string $taskName): void
    {
        try {
            $response = $this->httpClient->request('GET', sprintf(self::GET_URL, $this->namespace, $taskName), [
                'headers' => [
                    'Accept' => 'application/json',
                ],
                'auth_bearer' => $this->authenticationToken,
            ]);

            $job = $this->serializer->deserialize($response, CronJob::class, 'json');
            $job->set('spec', array_merge($job->get('spec'), ['suspend' => true]));

            $this->update($taskName, $job);
        } catch (\Throwable $exception) {
            throw new InvalidOperationException('The task cannot be updated: "%s"', $exception->getMessage());
        }
    }

    /**
     * {@inheritdoc}
     */
    public function resume(string $taskName): void
    {
        try {
            $response = $this->httpClient->request('GET', sprintf(self::GET_URL, $this->namespace, $taskName), [
                'headers' => [
                    'Accept' => 'application/json',
                ],
                'auth_bearer' => $this->authenticationToken,
            ]);

            $job = $this->serializer->deserialize($response, CronJob::class, 'json');
            if (null !== $job->get('spec') && $job->get('spec')['suspend'] === false) {
                throw new InvalidOperationException('The task cannot be resumed as the task is already allowed to run.');
            }

            $job->set('spec', array_merge($job->get('spec'), ['suspend' => false]));

            $this->update($taskName, $job);
        } catch (\Throwable $exception) {
            throw new InvalidOperationException('The task cannot be updated: "%s"', $exception->getMessage());
        }
    }

    /**
     * {@inheritdoc}
     */
    public function delete(string $taskName): void
    {
        try {
            $this->httpClient->request('DELETE', sprintf(self::DELETE_URL, $this->namespace, $taskName), [
                'auth_bearer' => $this->authenticationToken,
            ]);
        } catch (\Throwable $exception) {
            throw new InvalidOperationException(sprintf('The cron job cannot be deleted, error: "%s"', $exception->getMessage()));
        }
    }

    /**
     * {@inheritdoc}
     */
    public function empty(): void
    {
        try {
            $this->httpClient->request('DELETE', sprintf(self::DELETE_COLLECTION_URL, $this->namespace), [
                'auth_bearer' => $this->authenticationToken,
            ]);
        } catch (\Throwable $exception) {
            throw new InvalidOperationException(sprintf('The cron jobs list cannot be emptied, error: "%s"', $exception->getMessage()));
        }
    }

    private function warmClient(HttpClientInterface $httpClient): HttpClientInterface
    {
        return ScopingHttpClient::forBaseUri($httpClient, sprintf('%s://%s', $this->scheme, $this->apiUrl), [
            'headers' => [
                'Accept' => 'application/json',
            ],
        ]);
    }
}
