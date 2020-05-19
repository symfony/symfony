<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Scheduler\Tests\Task;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Scheduler\Task\CommandTask;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class CommandTaskTest extends TestCase
{
    public function testCommandCantBeCreatedWithInvalidArguments(): void
    {
        static::expectException(\InvalidArgumentException::class);
        new CommandTask('test', '', [], ['--env' => 'test']);
    }

    public function testCommandCanBeCreatedWithValidArguments(): void
    {
        $task = new CommandTask('test', 'app:foo', [
            'expression' => '+ 2 days',
        ], ['test'], ['--env' => 'test']);

        static::assertSame('app:foo', $task->getCommand());
        static::assertInstanceOf(\DateTimeInterface::class, $task->getExpression());
        static::assertArrayHasKey('tags', $task->getOptions());
        static::assertSame(['test'], $task->get('tags'));
        static::assertSame(['test'], $task->get('command_arguments'));
        static::assertSame(['--env' => 'test'], $task->get('command_options'));
    }
}
