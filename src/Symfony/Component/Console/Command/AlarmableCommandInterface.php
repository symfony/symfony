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
 * Interface for commands that listen to SIGALRM signals.
 */
interface AlarmableCommandInterface
{
    /**
     * The method will be called before the command is run and subsequently on each SIGALRM signal.
     *
     * @return int The alarm interval in seconds
     */
    public function getAlarmInterval(InputInterface $input): int;

    /**
     * The method will be called when the application is signaled with SIGALRM.
     *
     * @return int|false The exit code to return or false to continue the normal execution
     */
    public function handleAlarm(int|false $previousExitCode = 0): int|false;
}
