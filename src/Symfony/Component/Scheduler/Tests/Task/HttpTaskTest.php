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
use Symfony\Component\Scheduler\Task\HttpTask;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class HttpTaskTest extends TestCase
{
    public function testTaskCanBeCreated(): void
    {
        $task = new HttpTask('foo', 'https://symfony.com', 'GET');

        static::assertSame('https://symfony.com', $task->get('url'));
        static::assertSame('GET', $task->get('method'));
        static::assertEmpty($task->get('client_options'));
    }
}
