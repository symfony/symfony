<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Process\Messenger;

use Symfony\Component\Process\Process;

/**
 * @author Kevin Bond <kevinbond@gmail.com>
 */
final class RunProcessContext extends RunProcessMessage
{
    public readonly ?int $exitCode;
    public readonly ?string $output;
    public readonly ?string $errorOutput;

    public function __construct(RunProcessMessage $message, Process $process)
    {
        parent::__construct($message->command, $message->cwd, $message->env, $message->input, $message->timeout);

        $this->exitCode = $process->getExitCode();
        $this->output = $process->isOutputDisabled() ? null : $process->getOutput();
        $this->errorOutput = $process->isOutputDisabled() ? null : $process->getErrorOutput();
    }
}
