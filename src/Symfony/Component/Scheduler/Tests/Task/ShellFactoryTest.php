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
use Symfony\Component\Scheduler\Task\ShellTask;
use Symfony\Component\Scheduler\Task\ShellFactory;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class ShellFactoryTest extends TestCase
{
    public function testFactoryCannotSupportInvalidTaskType(): void
    {
        static::assertFalse((new ShellFactory())->support('test'));
    }

    public function testFactoryCanSupportValidTaskType(): void
    {
        static::assertTrue((new ShellFactory())->support('shell'));
    }

    public function testTaskCanBeCreated(): void
    {
        $factory = new ShellFactory();

        $task = $factory->create([
            'name' => 'foo',
            'command' => 'echo Symfony!',
        ]);

        static::assertInstanceOf(ShellTask::class, $task);
    }
}
