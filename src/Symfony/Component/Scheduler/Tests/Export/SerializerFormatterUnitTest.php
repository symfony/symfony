<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Scheduler\Tests\Export;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Scheduler\Export\SerializerFormatter;
use Symfony\Component\Scheduler\Serializer\TaskNormalizer;
use Symfony\Component\Scheduler\Task\ShellTask;
use Symfony\Component\Serializer\Encoder\JsonEncoder;
use Symfony\Component\Serializer\Serializer;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class SerializerFormatterUnitTest extends TestCase
{
    public function testFormatterCannotFormatWithoutValidFormat(): void
    {
        $serializer = new Serializer([], [new JsonEncoder()]);

        $formatter = new SerializerFormatter($serializer);

        static::assertFalse($formatter->support('xml'));
    }

    public function testFormatterCanFormatWithValidFormat(): void
    {
        $serializer = new Serializer([new TaskNormalizer()], [new JsonEncoder()]);
        $task = new ShellTask('foo', 'echo Symfony');

        $formatter = new SerializerFormatter($serializer);

        static::assertTrue($formatter->support('json'));

        $formattedTask = $formatter->format($task);

        static::assertStringContainsString('name', $formattedTask);
        static::assertStringContainsString('expression', $formattedTask);
        static::assertStringContainsString('last_execution', $formattedTask);
        static::assertStringContainsString('output', $formattedTask);
        static::assertStringContainsString('timezone', $formattedTask);
        static::assertStringContainsString('priority', $formattedTask);
        static::assertStringContainsString('scheduled_at', $formattedTask);
        static::assertStringContainsString('state', $formattedTask);
    }
}
