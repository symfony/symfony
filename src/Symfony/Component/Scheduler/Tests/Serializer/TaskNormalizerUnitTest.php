<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Scheduler\Tests\Serializer;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Scheduler\Serializer\TaskNormalizer;
use Symfony\Component\Scheduler\Task\ShellTask;
use Symfony\Component\Scheduler\Task\TaskInterface;
use Symfony\Component\Serializer\Exception\InvalidArgumentException;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class TaskNormalizerUnitTest extends TestCase
{
    public function testNormalizerCannotBeCalledOnNullData(): void
    {
        $normalizer = new TaskNormalizer();

        static::assertFalse($normalizer->supportsNormalization(new FooTask()));
    }

    public function testNormalizerCanBeCalledOnValidData(): void
    {
        $normalizer = new TaskNormalizer();
        $task = $this->createMock(TaskInterface::class);

        static::assertTrue($normalizer->supportsNormalization($task));
    }

    public function testNormalizerCannotNormalize(): void
    {
        $normalizer = new TaskNormalizer();

        static::expectException(InvalidArgumentException::class);

        $normalizer->normalize(new FooTask(), 'json');
    }

    public function testNormalizerCanNormalize(): void
    {
        $normalizer = new TaskNormalizer();

        $data = $normalizer->normalize(new ShellTask('bar', 'echo Symfony'), 'json');

        static::assertArrayHasKey('name', $data);
        static::assertArrayHasKey('expression', $data);
        static::assertArrayHasKey('last_execution', $data);
        static::assertArrayHasKey('output', $data);
        static::assertArrayHasKey('timezone', $data);
        static::assertArrayHasKey('priority', $data);
        static::assertArrayHasKey('scheduled_at', $data);
        static::assertArrayHasKey('state', $data);
    }
}

final class FooTask
{
}
