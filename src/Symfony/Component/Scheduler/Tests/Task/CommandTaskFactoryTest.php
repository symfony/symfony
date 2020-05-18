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
use Symfony\Component\Scheduler\Task\CommandFactory;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class CommandTaskFactoryTest extends TestCase
{
    public function testTaskCanBeCreated(): void
    {
        $factory = new CommandFactory();

        $task = $factory->create([
            'name' => 'foo',
            'command' => 'cache:clear',
        ]);

        static::assertInstanceOf(CommandTask::class, $task);
    }
}
