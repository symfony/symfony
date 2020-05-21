<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Scheduler\Tests\Runner;

use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Component\Scheduler\Runner\HttpTaskRunner;
use Symfony\Component\Scheduler\Task\HttpTask;
use Symfony\Component\Scheduler\Task\NullTask;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class HttpTaskRunnerTest extends TestCase
{
    public function testRunnerCannotSupportWrongTask(): void
    {
        $runner = new HttpTaskRunner();

        static::assertFalse($runner->support(new NullTask('foo')));
    }

    public function testRunnerCanSupportValidTask(): void
    {
        $runner = new HttpTaskRunner();

        static::assertTrue($runner->support(new HttpTask('foo', 'https://symfony.com', 'GET')));
    }

    public function testRunnerCanGenerateErrorOutput(): void
    {
        $httpClient = new MockHttpClient([
            new MockResponse(json_encode([
                'error' => 404,
                'message' => 'Resource not found',
            ]), ['http_code' => 404])
        ]);

        $runner = new HttpTaskRunner($httpClient);
        $output = $runner->run(new HttpTask('foo', 'https://symfony.com', 'GET'));

        static::assertSame(404, $output->getExitCode());
        static::assertSame('HTTP 404 returned for "https://symfony.com/".', $output->getOutput());
    }

    public function testRunnerCanGenerateSuccessOutput(): void
    {
        $httpClient = new MockHttpClient([
            new MockResponse(json_encode([
                'body' => 'test',
            ]), ['http_code' => 200])
        ]);

        $runner = new HttpTaskRunner($httpClient);
        $output = $runner->run(new HttpTask('foo', 'https://symfony.com', 'GET'));

        static::assertSame(0, $output->getExitCode());
        static::assertSame('{"body":"test"}', $output->getOutput());
    }
}
