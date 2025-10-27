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
final readonly class RunProcessContext
{
    public ?int $exitCode;
    public ?string $output;
    public ?string $errorOutput;

    public function __construct(
        public RunProcessMessage $message,
        Process $process,
    ) {
        $this->exitCode = $process->getExitCode();
        $this->output = !$process->isStarted() || $process->isOutputDisabled() ? null : $process->getOutput();
        $this->errorOutput = !$process->isStarted() || $process->isOutputDisabled() ? null : $process->getErrorOutput();
    }
}
