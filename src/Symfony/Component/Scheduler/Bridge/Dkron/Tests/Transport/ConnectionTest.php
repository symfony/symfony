<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Scheduler\Bridge\Dkron\Tests\Transport;

use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Component\Scheduler\Bridge\Dkron\Transport\Connection;
use Symfony\Component\Serializer\SerializerInterface;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class ConnectionTest extends TestCase
{
    public function testConnectionCannotDeleteWithException(): void
    {
    }

    public function testConnectionCanDelete(): void
    {
        $serializer = $this->createMock(SerializerInterface::class);
        $serializer->expects(self::never())->method('deserialize');

        $httpClient = new MockHttpClient([
            new MockResponse(json_encode([
                "name" => "foo",
                "displayname" => "string",
                "schedule" => "@every 10s",
                "timezone" => "Europe/Berlin",
                "owner" => "Platform Team",
                "owner_email" => "platform@example.com",
                "success_count" => 0,
                "error_count" => 0,
                "last_success" => "2020-05-31T16:51:42.806Z",
                "last_error" => "2020-05-31T16:51:42.806Z",
                "disabled" => true,
                "tags" => [
                    "server" => "true",
                ],
                "metadata" => [
                    "office" => "Barcelona",
                ],
                "retries" => 2,
                "parent_job" => "parent_job",
                "dependent_jobs" => [
                    "dependent_job"
                ],
                "processors" => [
                    "files" => [
                        "forward" => true,
                    ],
                ],
                "concurrency" => "allow",
                "executor" => "shell",
                "executor_config" => [
                    "command" => "echo 'Hello from Dkron'"
                ],
                "status" => "success",
            ]))
        ], 'http://127.0.0.1');

        $connection = new Connection([], $serializer, $httpClient);
        $connection->delete('foo');
    }
}
