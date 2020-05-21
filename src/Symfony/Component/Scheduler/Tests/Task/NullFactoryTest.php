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
use Symfony\Component\Scheduler\Task\NullFactory;
use Symfony\Component\Scheduler\Task\NullTask;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class NullFactoryTest extends TestCase
{
    public function testFactoryCannotSupportInvalidTaskType(): void
    {
        static::assertFalse((new NullFactory())->support('test'));
    }

    public function testFactoryCanSupportValidTaskType(): void
    {
        static::assertTrue((new NullFactory())->support('null'));
    }

    public function testTaskCanBeCreated(): void
    {
        $factory = new NullFactory();

        $task = $factory->create([
            'name' => 'foo',
        ]);

        static::assertInstanceOf(NullTask::class, $task);
    }
}
