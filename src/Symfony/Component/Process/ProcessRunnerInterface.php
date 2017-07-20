<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Process;

use Symfony\Component\Process\Exception\LogicException;
use Symfony\Component\Process\Exception\RuntimeException;

/**
 * @author John Nickell <email@johnnickell.com>
 */
interface ProcessRunnerInterface
{
    const EXCEPTION_ON_ERROR = 1;
    const IGNORE_ON_ERROR = 2;

    /**
     * Adds a process.
     *
     * The callback receives the type of output (out or err) and some bytes from
     * the output in real-time while writing the standard input to the process.
     * It allows to have feedback from the independent process during execution.
     *
     * @param Process       $process  The process
     * @param callable|null $callback A PHP callback to run whenever there is some
     *                                output available on STDOUT or STDERR
     */
    public function add(Process $process, callable $callback = null);

    /**
     * Clears attached processes.
     */
    public function clear();

    /**
     * Runs the attached processes.
     *
     * @param int $errorBehavior The behavior when a process fails
     *
     * @throws RuntimeException When a process can't be launched
     * @throws RuntimeException When a process stopped after receiving signal
     * @throws RuntimeException When a process fails, depending on error behavior
     * @throws LogicException   In case a callback is provided and output has been disabled
     */
    public function run($errorBehavior = self::EXCEPTION_ON_ERROR);
}
