<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Scheduler\Bridge\Google\Transport;

use Symfony\Component\HttpClient\Exception\ClientException;
use Symfony\Component\HttpClient\ScopingHttpClient;
use Symfony\Component\Scheduler\Bridge\Google\Exception\InvalidConfigurationException;
use Symfony\Component\Scheduler\Bridge\Google\Exception\InvalidJobException;
use Symfony\Component\Scheduler\Bridge\Google\Task\JobFactory;
use Symfony\Component\Scheduler\Exception\InvalidArgumentException;
use Symfony\Component\Scheduler\Task\TaskInterface;
use Symfony\Component\Scheduler\Transport\Dsn;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class Connection
{
    private const BASE_URI = 'https://cloudscheduler.googleapis.com/';

    /**
     * @see https://cloud.google.com/scheduler/docs/reference/rest/v1/projects.locations.jobs/create
     */
    private const POST_URL = 'v1/%s/jobs';

    /**
     * @see https://cloud.google.com/scheduler/docs/reference/rest/v1/projects.locations.jobs/delete
     */
    private const DELETE_URL = 'v1/%s';

    /**
     * @see https://cloud.google.com/scheduler/docs/reference/rest/v1/projects.locations.jobs/get
     */
    private const GET_URL = 'v1/%s';

    /**
     * @see https://cloud.google.com/scheduler/docs/reference/rest/v1/projects.locations.jobs/list
     */
    private const LIST_URL = 'v1/%s/jobs';

    /**
     * @see https://cloud.google.com/scheduler/docs/reference/rest/v1/projects.locations.jobs/patch
     */
    private const PATCH_URL = 'v1/%s';

    /**
     * @see https://cloud.google.com/scheduler/docs/reference/rest/v1/projects.locations.jobs/pause
     */
    private const PAUSE_URL = 'v1/%s:pause';

    /**
     * @see https://cloud.google.com/scheduler/docs/reference/rest/v1/projects.locations.jobs/run
     */
    private const RUN_URL = 'v1/%s:run';

    private $bearerToken;
    private $authenticationKey;
    private $projectConfiguration;
    private $httpClient;
    private $factory;

    public function __construct(Dsn $dsn, HttpClientInterface $httpClient, JobFactory $factory)
    {
        $this->bearerToken = $dsn->getOption('bearer_token');
        $this->authenticationKey = $dsn->getOption('auth_key');;
        $this->projectConfiguration = sprintf('projects/%s/locations/%s', $dsn->getUser(), $dsn->getHost());
        $this->httpClient = $this->warmClient($httpClient);
        $this->factory = $factory;
    }

    public function create(TaskInterface $job): void
    {
        $url = $this->validateUrl($this->projectConfiguration, self::POST_URL, '#^projects\/[^\/]+\/locations\/[^\/]+$#');
        $this->validateJobName($job->getName());

        $response = $this->httpClient->request('POST', $url, [
            'json' => $job->toArray(),
        ]);

        if (200 !== $response->getStatusCode()) {
            throw new InvalidJobException('The given job cannot be scheduled');
        }

        $body = $response->toArray();

        $job->set('user_update_time', $body['userUpdateTime']);
        $job->set('state', $body['state']);
        $job->set('schedule_time', $body['scheduleTime']);
        $job->set('last_attempt_time', $body['lastAttemptTime']);
    }

    public function delete(string $jobName): void
    {
        $url = $this->validateUrl(sprintf('%s/jobs/%s', $this->projectConfiguration, $jobName), self::DELETE_URL, '#^projects\/[^\/]+\/locations\/[^\/]+\/jobs\/[^\/]+$#');

        $response = $this->httpClient->request('DELETE', $url);

        if (200 !== $response->getStatusCode()) {
            throw new InvalidJobException('The given job cannot be deleted');
        }
    }

    public function get(string $jobName): TaskInterface
    {
        $url = $this->validateUrl(sprintf('%s/jobs/%s', $this->projectConfiguration, $jobName), self::GET_URL, '#^projects\/[^\/]+\/locations\/[^\/]+\/jobs\/[^\/]+$#');

        try {
            $response = $this->httpClient->request('GET', $url);

            return $this->factory->create($response->toArray());
        } catch (ClientException $exception) {
            $response = $exception->getResponse();

            if (404 === $response->getStatusCode()) {
                throw new InvalidJobException(sprintf('The given job cannot be returned because it does not exist!'));
            }
        }
    }

    /**
     * @param array $queryParameters A valid array of parameters {@see https://cloud.google.com/scheduler/docs/reference/rest/v1/projects.locations.jobs/list#query-parameters}
     *
     * @return array The stored tasks
     *
     * @throws \Symfony\Contracts\HttpClient\Exception\ClientExceptionInterface
     * @throws \Symfony\Contracts\HttpClient\Exception\DecodingExceptionInterface
     * @throws \Symfony\Contracts\HttpClient\Exception\RedirectionExceptionInterface
     * @throws \Symfony\Contracts\HttpClient\Exception\ServerExceptionInterface
     * @throws \Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface
     */
    public function list(array $queryParameters = []): array
    {
        $url = $this->validateUrl($this->projectConfiguration, self::LIST_URL, '#^projects\/[^\/]+\/locations\/[^\/]+$#');

        $finalQueryParameters = [];
        $finalQueryParameters['key'] = $this->authenticationKey;

        if (!empty($queryParameters)) {
            foreach ($queryParameters as $key => $parameter) {
                if (\in_array($key, ['pageSize', 'pageToken'])) {
                    $finalQueryParameters[$key] = $parameter;
                }
            }
        }

        $response = $this->httpClient->request('GET', $url, [
            'query' => $finalQueryParameters,
        ]);

        if (200 !== $response->getStatusCode()) {
            throw new InvalidJobException('The jobs cannot be listed!');
        }

        $body = $response->toArray();

        if (!\array_key_exists('jobs', $body)) {
            throw new InvalidArgumentException('The API response body seems to be invalid, please check the request!');
        }

        return $this->factory->createFromArray($body['jobs']);
    }

    /**
     * @param string $updateMask {@see https://cloud.google.com/scheduler/docs/reference/rest/v1/projects.locations.jobs/patch#query-parameters}
     *
     * @return TaskInterface
     *
     * @throws \Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface
     */
    public function patch(string $jobName, TaskInterface $body, ?string $updateMask = null): TaskInterface
    {
        $url = $this->validateUrl(sprintf('%s/jobs/%s', $this->projectConfiguration, $jobName), self::PATCH_URL, '#^projects\/[^\/]+\/locations\/[^\/]+\/jobs\/[^\/]+$#');

        try {
            $requestData = [
                'json' => $body->toArray(),
            ];

            if (null !== $updateMask) {
                $requestData['query'] = [
                    'updateMask' => $updateMask
                ];
            }

            $response = $this->httpClient->request('PATCH', $url, $requestData);

            return $this->factory->create($response->toArray());
        } catch (ClientException $exception) {
            $response = $exception->getResponse();

            if (404 === $response->getStatusCode()) {
                throw new InvalidJobException(sprintf('The given job cannot be updated because it does not exist!'));
            }
        }
    }

    public function pause(string $taskName): TaskInterface
    {
        $url = $this->validateUrl(sprintf('%s/jobs/%s', $this->projectConfiguration, $taskName), self::PAUSE_URL, '#^projects\/[^\/]+\/locations\/[^\/]+\/jobs\/[^\/]+$#');

        try {
            $response = $this->httpClient->request('POST', $url);

            return $this->factory->create($response->toArray());
        } catch (ClientException $exception) {
            $response = $exception->getResponse();

            if (404 === $response->getStatusCode()) {
                throw new InvalidJobException(sprintf('The given job cannot be paused because it does not exist!'));
            }
        }
    }

    public function resume(string $taskName): TaskInterface
    {
        $url = $this->validateUrl(sprintf('%s/jobs/%s', $this->projectConfiguration, $taskName), self::RUN_URL, '#^projects\/[^\/]+\/locations\/[^\/]+\/jobs\/[^\/]+$#');

        try {
            $response = $this->httpClient->request('POST', $url);

            return $this->factory->create($response->toArray());
        } catch (ClientException $exception) {
            $response = $exception->getResponse();

            if (404 === $response->getStatusCode()) {
                throw new InvalidJobException(sprintf('The given job cannot be resumed because it does not exist!'));
            }
        }
    }

    public function run(string $jobName): TaskInterface
    {
        $url = $this->validateUrl(sprintf('%s/jobs/%s', $this->projectConfiguration, $jobName), self::RUN_URL, '#^projects\/[^\/]+\/locations\/[^\/]+\/jobs\/[^\/]+$#');

        try {
            $response = $this->httpClient->request('POST', $url);

            return $this->factory->create($response->toArray());
        } catch (ClientException $exception) {
            $response = $exception->getResponse();

            if (404 === $response->getStatusCode()) {
                throw new InvalidJobException(sprintf('The given job cannot run because it does not exist!'));
            }
        }
    }

    private function validateUrl(string $configuration, string $url, $regex): string
    {
        if (!preg_match($regex, $configuration, $matches)) {
            throw new InvalidConfigurationException('The given configuration cannot be validated, please check the informations!');
        }

        return sprintf($url, $matches[0]);
    }

    private function validateJobName(string $name): void
    {
        if (!preg_match('#[a-zA-Z0-9\-\.\:]{0,500}+#', $name, $matches)) {
            throw new \InvalidArgumentException(sprintf('The given name %s is invalid!', $name));
        }
    }

    private function warmClient(HttpClientInterface $httpClient): HttpClientInterface
    {
        return ScopingHttpClient::forBaseUri($httpClient, self::BASE_URI, [
            'auth_bearer' => $this->bearerToken,
            'headers' => [
                'Accept' => 'application/json',
                'Content-Type' => 'application/json',
            ],
            'query' => [
                'key' => $this->authenticationKey,
            ],
        ]);
    }
}
