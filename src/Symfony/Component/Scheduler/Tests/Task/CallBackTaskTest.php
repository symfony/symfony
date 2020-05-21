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
use Symfony\Component\Scheduler\Task\CallBackTask;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class CallBackTaskTest extends TestCase
{
    public function testTaskCannotBeCreatedWithInvalidCallback(): void
    {
        static::expectException(\InvalidArgumentException::class);
        new CallBackTask('foo', [$this, 'test']);
    }

    public function testTaskCanBeCreatedWithValidCallback(): void
    {
        $task = new CallBackTask('foo', function () {
            echo 'test';
        });

        static::assertEmpty($task->get('arguments'));
    }

    public function testTaskCanBeCreatedWithValidCallbackAndArguments(): void
    {
        $task = new CallBackTask('foo', function ($value) {
            echo $value;
        }, ['value' => 'test']);

        static::assertNotEmpty($task->get('arguments'));
    }
}
