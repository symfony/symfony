<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Console\Command;

use Symfony\Component\Console\Input\InputInterface;

/**
 * Interface for command that listens to SIGALRM signals.
 */
interface AlarmableCommandInterface
{
    /**
     * The method will be called before the command is run and subsequently on each SIGALRM signal.
     *
     * @return int The alarm time in seconds
     */
    public function getAlarmTime(InputInterface $input): int;

    /**
     * The method will be called when the application is signaled with SIGALRM.
     *
     * @return int|false The exit code to return or false to continue the normal execution
     */
    public function handleAlarm(int|false $previousExitCode = 0): int|false;
}
