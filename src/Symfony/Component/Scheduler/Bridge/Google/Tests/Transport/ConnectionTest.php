<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Scheduler\Bridge\Google\Tests\Transport;

use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Component\Scheduler\Bridge\Google\Exception\InvalidConfigurationException;
use Symfony\Component\Scheduler\Bridge\Google\Exception\InvalidJobException;
use Symfony\Component\Scheduler\Bridge\Google\Task\Job;
use Symfony\Component\Scheduler\Bridge\Google\Task\JobFactory;
use Symfony\Component\Scheduler\Bridge\Google\Task\State;
use Symfony\Component\Scheduler\Bridge\Google\Transport\Connection;
use Symfony\Component\Scheduler\Task\TaskInterface;
use Symfony\Component\Scheduler\Transport\Dsn;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class ConnectionTest extends TestCase
{
    public function testClientCannotCreateWithInvalidConfiguration(): void
    {
        $httpClient = $this->createMock(HttpClientInterface::class);
        $client = new Connection(Dsn::fromString('google://@project?auth_key=test&bearer=test'), $httpClient, new JobFactory());

        static::expectException(InvalidConfigurationException::class);
        $client->create(new Job('test'));
    }

    /**
     * @param TaskInterface $task
     *
     * @dataProvider provideJobs
     *
     * @throws \Exception
     */
    public function testClientCanCreate(TaskInterface $task): void
    {
        $httpClient = new MockHttpClient([
            new MockResponse(json_encode([
                'name' => 'test',
                'description' => '',
                'schedule' => '* * * * *',
                'timeZone' => 'Europe/Paris',
                'userUpdateTime' => (new \DateTimeImmutable())->format('Y-m-d\TH:i:s\Z'),
                'state' => State::ENABLED,
                'scheduleTime' => (new \DateTimeImmutable('+ 1 hour'))->format('Y-m-d\TH:i:s\Z'),
                'lastAttemptTime' => (new \DateTimeImmutable())->format('Y-m-d\TH:i:s\Z'),
            ])),
        ]);

        $factory = new JobFactory();
        $client = new Connection(Dsn::fromString('google://tests@europe-west1?auth_key=test&bearer=test'), $httpClient, $factory);
        $client->create($task);

        static::assertNotNull($task->get('user_update_time'));
        static::assertSame(State::ENABLED, $task->get('state'));
        static::assertNotNull($task->get('schedule_time'));
        static::assertNotNull($task->get('last_attempt_time'));
    }

    public function testClientCannotDeleteWithInvalidConfiguration(): void
    {
        $httpClient = $this->createMock(HttpClientInterface::class);

        $factory = new JobFactory();
        $client = new Connection(Dsn::fromString('google://@europe-west1?auth_key=test&bearer=test'), $httpClient, $factory);

        static::expectException(InvalidConfigurationException::class);
        $client->delete('test');
    }

    public function testClientCannotGetWithInvalidConfiguration(): void
    {
        $httpClient = $this->createMock(HttpClientInterface::class);

        $factory = new JobFactory();
        $client = new Connection(Dsn::fromString('google://@test?auth_key=test&bearer=test'), $httpClient, $factory);

        static::expectException(InvalidConfigurationException::class);
        $client->get('test');
    }

    public function testClientCannotGet(): void
    {
        $httpClient = new MockHttpClient([
            new MockResponse(json_encode([
                'error' => [
                    'code' => 404,
                    'message' => 'Job not found.',
                    'status' => 'NOT_FOUND',
                ]
            ]), ['http_code' => 404]),
        ]);
        $factory = new JobFactory();
        $client = new Connection(Dsn::fromString('google://tests@europe-west1?auth_key=test&bearer=test'), $httpClient, $factory);

        static::expectException(InvalidJobException::class);
        $client->get('test');
    }

    public function testClientCanGet(): void
    {
        $httpClient = new MockHttpClient([
            new MockResponse(json_encode([
                'name' => 'test',
                'description' => 'A random task',
                'schedule' => '* * * * *',
                'timeZone' => 'Europe/Paris',
                'userUpdateTime' => (new \DateTimeImmutable())->format('Y-m-d\TH:i:s\Z'),
                'state' => State::ENABLED,
                'scheduleTime' => (new \DateTimeImmutable('+ 1 hour'))->format('Y-m-d\TH:i:s\Z'),
                'lastAttemptTime' => (new \DateTimeImmutable())->format('Y-m-d\TH:i:s\Z'),
            ])),
        ]);
        $factory = new JobFactory();
        $client = new Connection(Dsn::fromString('google://tests@europe-west1?auth_key=test&bearer=test'), $httpClient, $factory);
        $job = $client->get('test');

        static::assertInstanceOf(TaskInterface::class, $job);
        static::assertSame('test', $job->getName());
        static::assertSame('A random task', $job->get('description'));
    }

    public function testClientCanList(): void
    {
        $httpClient = new MockHttpClient([
            new MockResponse(json_encode([
                'jobs' => [
                    [
                        'name' => 'foo',
                        'description' => 'A random task',
                        'schedule' => '* * * * *',
                        'timeZone' => 'Europe/Paris',
                        'userUpdateTime' => (new \DateTimeImmutable())->format('Y-m-d\TH:i:s\Z'),
                        'state' => State::ENABLED,
                        'scheduleTime' => (new \DateTimeImmutable('+ 1 hour'))->format('Y-m-d\TH:i:s\Z'),
                        'lastAttemptTime' => (new \DateTimeImmutable())->format('Y-m-d\TH:i:s\Z'),
                    ],
                    [
                        'name' => 'bar',
                        'description' => 'A random task',
                        'schedule' => '* * * * *',
                        'timeZone' => 'Europe/Paris',
                        'userUpdateTime' => (new \DateTimeImmutable())->format('Y-m-d\TH:i:s\Z'),
                        'state' => State::ENABLED,
                        'scheduleTime' => (new \DateTimeImmutable('+ 1 hour'))->format('Y-m-d\TH:i:s\Z'),
                        'lastAttemptTime' => (new \DateTimeImmutable())->format('Y-m-d\TH:i:s\Z'),
                    ]
                ]
            ])),
        ]);
        $factory = new JobFactory();
        $client = new Connection(Dsn::fromString('google://tests@europe-west1?auth_key=test&bearer=test'), $httpClient, $factory);

        $jobs = $client->list();
        static::assertNotEmpty($jobs);
    }

    public function testClientCannotPatch(): void
    {
        $httpClient = new MockHttpClient([
            new MockResponse(json_encode([
                'error' => [
                    'code' => 404,
                    'message' => 'Job not found.',
                    'status' => 'NOT_FOUND',
                ]
            ]), ['http_code' => 404]),
        ]);
        $factory = new JobFactory();
        $client = new Connection(Dsn::fromString('google://tests@europe-west1?auth_key=test&bearer=test'), $httpClient, $factory);

        static::expectException(InvalidJobException::class);
        $client->patch('bar', new Job('test'), 'task.description');
    }

    public function testClientCanPatch(): void
    {
        $httpClient = new MockHttpClient([
            new MockResponse(json_encode([
                'name' => 'bar',
                'description' => 'A new random description',
                'schedule' => '* * * * *',
                'timeZone' => 'Europe/Paris',
                'userUpdateTime' => (new \DateTimeImmutable())->format('Y-m-d\TH:i:s\Z'),
                'state' => State::ENABLED,
                'scheduleTime' => (new \DateTimeImmutable('+ 1 hour'))->format('Y-m-d\TH:i:s\Z'),
                'lastAttemptTime' => (new \DateTimeImmutable())->format('Y-m-d\TH:i:s\Z'),
            ])),
        ]);
        $factory = new JobFactory();
        $client = new Connection(Dsn::fromString('google://tests@europe-west1?auth_key=test&bearer=test'), $httpClient, $factory);
        $job = $client->patch('bar', new Job('test'), 'task.description');

        static::assertInstanceOf(Job::class, $job);
    }

    public function testClientCannotPause(): void
    {
        $httpClient = new MockHttpClient([
            new MockResponse(json_encode([
                'error' => [
                    'code' => 404,
                    'message' => 'Job not found.',
                    'status' => 'NOT_FOUND',
                ]
            ]), ['http_code' => 404]),
        ]);
        $factory = new JobFactory();
        $client = new Connection(Dsn::fromString('google://tests@europe-west1?auth_key=test&bearer=test'), $httpClient, $factory);

        static::expectException(InvalidJobException::class);
        $client->pause('bar');
    }

    public function testClientCanPause(): void
    {
        $httpClient = new MockHttpClient([
            new MockResponse(json_encode([
                'name' => 'bar',
                'description' => 'A new random description',
                'schedule' => '* * * * *',
                'timeZone' => 'Europe/Paris',
                'userUpdateTime' => (new \DateTimeImmutable())->format('Y-m-d\TH:i:s\Z'),
                'state' => State::PAUSED,
                'scheduleTime' => (new \DateTimeImmutable('+ 1 hour'))->format('Y-m-d\TH:i:s\Z'),
                'lastAttemptTime' => (new \DateTimeImmutable())->format('Y-m-d\TH:i:s\Z'),
            ])),
        ]);
        $factory = new JobFactory();
        $client = new Connection(Dsn::fromString('google://tests@europe-west1?auth_key=test&bearer=test'), $httpClient, $factory);
        $job = $client->pause('bar');

        static::assertInstanceOf(Job::class, $job);
        static::assertSame(State::PAUSED, $job->get('state'));
    }

    public function testClientCannotResume(): void
    {
        $httpClient = new MockHttpClient([
            new MockResponse(json_encode([
                'error' => [
                    'code' => 404,
                    'message' => 'Job not found.',
                    'status' => 'NOT_FOUND',
                ]
            ]), ['http_code' => 404]),
        ]);
        $factory = new JobFactory();
        $client = new Connection(Dsn::fromString('google://tests@europe-west1?auth_key=test&bearer=test'), $httpClient, $factory);

        static::expectException(InvalidJobException::class);
        $client->resume('bar');
    }

    public function testClientCanResume(): void
    {
        $httpClient = new MockHttpClient([
            new MockResponse(json_encode([
                'name' => 'bar',
                'description' => 'A new random description',
                'schedule' => '* * * * *',
                'timeZone' => 'Europe/Paris',
                'userUpdateTime' => (new \DateTimeImmutable())->format('Y-m-d\TH:i:s\Z'),
                'state' => State::ENABLED,
                'scheduleTime' => (new \DateTimeImmutable('+ 1 hour'))->format('Y-m-d\TH:i:s\Z'),
                'lastAttemptTime' => (new \DateTimeImmutable())->format('Y-m-d\TH:i:s\Z'),
            ])),
        ]);
        $factory = new JobFactory();
        $client = new Connection(Dsn::fromString('google://tests@europe-west1?auth_key=test&bearer=test'), $httpClient, $factory);
        $job = $client->resume('bar');

        static::assertInstanceOf(Job::class, $job);
        static::assertSame(State::ENABLED, $job->get('state'));
    }

    public function testClientCannotRun(): void
    {
        $httpClient = new MockHttpClient([
            new MockResponse(json_encode([
                'error' => [
                    'code' => 404,
                    'message' => 'Job not found.',
                    'status' => 'NOT_FOUND',
                ]
            ]), ['http_code' => 404]),
        ]);
        $factory = new JobFactory();
        $client = new Connection(Dsn::fromString('google://tests@europe-west1?auth_key=test&bearer=test'), $httpClient, $factory);

        static::expectException(InvalidJobException::class);
        $client->run('bar');
    }

    public function testClientCanRun(): void
    {
        $httpClient = new MockHttpClient([
            new MockResponse(json_encode([
                'name' => 'bar',
                'description' => 'A new random description',
                'schedule' => '* * * * *',
                'timeZone' => 'Europe/Paris',
                'userUpdateTime' => (new \DateTimeImmutable())->format('Y-m-d\TH:i:s\Z'),
                'state' => State::ENABLED,
                'scheduleTime' => (new \DateTimeImmutable('+ 1 hour'))->format('Y-m-d\TH:i:s\Z'),
                'lastAttemptTime' => (new \DateTimeImmutable())->format('Y-m-d\TH:i:s\Z'),
            ])),
        ]);
        $factory = new JobFactory();
        $client = new Connection(Dsn::fromString('google://tests@europe-west1?auth_key=test&bearer=test'), $httpClient, $factory);
        $job = $client->run('bar');

        static::assertInstanceOf(Job::class, $job);
        static::assertSame(State::ENABLED, $job->get('state'));
    }

    public function provideJobs(): \Generator
    {
        yield [
            new Job('test'),
        ];
    }
}
