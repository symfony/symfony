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
use Symfony\Component\Scheduler\Exception\InvalidArgumentException;
use Symfony\Component\Scheduler\Task\TaskFactory;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class TaskFactoryTest extends TestCase
{
    /**
     * @dataProvider provideTaskInformations
     */
    public function testFactoryCannotBeCalledOnceEmpty(array $taskInformations): void
    {
        static::expectException(InvalidArgumentException::class);
        (new TaskFactory([]))->create($taskInformations);
    }

    public function provideTaskInformations(): \Generator
    {
        yield [
            [
                'type' => 'shell',
                'name' => 'app.foo',
                'expression' => '* * * * *',
            ],
            [
                'type' => 'null',
                'name' => 'app.bar',
                'expression' => '5 * * * *',
            ],
        ];
    }
}
