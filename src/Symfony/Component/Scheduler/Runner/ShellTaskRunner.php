<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Scheduler\Runner;

use Symfony\Component\Process\Process;
use Symfony\Component\Scheduler\Task\Output;
use Symfony\Component\Scheduler\Task\ShellTask;
use Symfony\Component\Scheduler\Task\TaskInterface;

/**
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class ShellTaskRunner implements RunnerInterface
{
    /**
     * {@inheritdoc}
     */
    public function run(TaskInterface $task): Output
    {
        $process = Process::fromShellCommandline(
            $task->getCommand(),
            $task->get('cwd'),
            $task->get('env'),
            $task->get('input'),
            $task->get('timeout')
        );

        $exitCode = $process->run(null, $task->get('arguments'));

        $output = $task->get('output') ? trim($process->getOutput()) : null;

        return 0 === $exitCode
            ? Output::forSuccess($task, $exitCode, $output)
            : Output::forError($task, $exitCode, $output)
        ;
    }

    /**
     * {@inheritdoc}
     */
    public function support(TaskInterface $task): bool
    {
        return $task instanceof ShellTask;
    }
}
