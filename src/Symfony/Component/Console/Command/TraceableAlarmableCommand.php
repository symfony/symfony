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
use Symfony\Component\Stopwatch\Stopwatch;

/**
 * @internal
 *
 * @property Command&AlarmableCommandInterface $command
 */
final class TraceableAlarmableCommand extends TraceableCommand implements AlarmableCommandInterface
{
    public function __construct(Command&AlarmableCommandInterface $command, Stopwatch $stopwatch)
    {
        parent::__construct($command, $stopwatch);
    }

    public function getSubscribedSignals(): array
    {
        $commandSignals = parent::getSubscribedSignals();

        if (!\in_array(\SIGALRM, $commandSignals, true)) {
            $commandSignals[] = \SIGALRM;
        }

        return $commandSignals;
    }

    public function getAlarmInterval(InputInterface $input): int
    {
        return $this->command->getAlarmInterval($input);
    }

    public function handleAlarm(false|int $previousExitCode = 0): int|false
    {
        $event = $this->stopwatch->start($this->getName().'.handle_alarm');

        $exit = $this->command->handleAlarm($previousExitCode);

        $event->stop();

        $this->recordHandledSignal(\SIGALRM, $event);

        return $exit;
    }
}
