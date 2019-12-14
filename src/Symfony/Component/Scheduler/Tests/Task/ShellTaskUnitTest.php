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

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class ShellTaskUnitTest extends TestCase
{
    public function testTaskCanBeCreatedWithValidInformations(): void
    {
        $task = new ShellTask('foo', 'echo Symfony!');

        static::assertSame('echo Symfony!', $task->getCommand());
    }
}
