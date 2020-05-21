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
use Symfony\Component\Scheduler\Task\TaskInterface;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class TaskNormalizerUnitTest extends TestCase
{
    public function testNormalizerCannotNormalizeInvalidObject(): void
    {
        $normalizer = new TaskNormalizer();

        static::assertFalse($normalizer->supportsNormalization(new FooTask(), 'json'));
    }

    public function testNormalizerCanNormalizeValidObject(): void
    {
        $task = $this->createMock(TaskInterface::class);

        $normalizer = new TaskNormalizer();

        static::assertTrue($normalizer->supportsNormalization($task, 'json'));
    }
}

final class FooTask
{
}
