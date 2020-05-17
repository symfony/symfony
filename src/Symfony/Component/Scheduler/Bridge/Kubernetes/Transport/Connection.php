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

use Symfony\Component\HttpClient\Exception\ClientException;
use Symfony\Component\HttpClient\ScopingHttpClient;
use Symfony\Component\Scheduler\Bridge\Kubernetes\Exception\InvalidOperationException;
use Symfony\Component\Scheduler\Task\TaskInterface;
use Symfony\Component\Scheduler\Transport\ConnectionInterface;
use Symfony\Component\Scheduler\Transport\Dsn;
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
     * https://kubernetes.io/docs/reference/generated/kubernetes-api/%apiVersion%/#path-cronjob-v1beta1-batch
     */
    private const PATCH_URL = '/apis/batch/v1beta1/namespaces/%s/cronjobs/%s';

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
    private $httpClient;
    private $namespace;
    private $scheme;

    public function __construct(Dsn $dsn, HttpClientInterface $httpClient)
    {
        $this->apiUrl = $dsn->getHost();
        $this->namespace = $dsn->getOption('namespace');
        $this->scheme = $dsn->getOption('scheme') ?? 'https';
        $this->httpClient = $this->warmClient($httpClient);
    }

    public function create(TaskInterface $task): void
    {
    }

    /**
     * {@inheritdoc}
     */
    public function list(): array
    {
    }

    /**
     * {@inheritdoc}
     */
    public function pause(string $taskName): void
    {
    }

    /**
     * {@inheritdoc}
     */
    public function resume(string $taskName): void
    {
    }

    /**
     * {@inheritdoc}
     */
    public function delete(string $taskName): void
    {
        try {
            $this->httpClient->request('DELETE', sprintf(self::DELETE_URL, $this->namespace, $taskName));
        } catch (ClientException $exception) {
            $response = $exception->getResponse();

            if (!\in_array($response->getStatusCode(), [200, 202])) {
                throw new InvalidOperationException('The cron job cannot be deleted');
            }
        }
    }

    /**
     * {@inheritdoc}
     */
    public function empty(): void
    {
        try {
            $this->httpClient->request('DELETE', sprintf(self::DELETE_COLLECTION_URL, $this->namespace));
        } catch (ClientException $exception) {
            $response = $exception->getResponse();

            if (200 !== $response->getStatusCode()) {
                throw new InvalidOperationException('The cron jobs list cannot be emptied');
            }
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
