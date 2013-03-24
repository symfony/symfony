<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Console;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\ConsoleEvents;
use Symfony\Component\Console\Event\ConsoleCommandEvent;
use Symfony\Component\Console\Event\ConsoleTerminateEvent;
use Symfony\Component\Console\Event\ConsoleForExceptionEvent;
use Symfony\Component\EventDispatcher\EventDispatcher;

/**
 * Optionally dispatches events during the life time of a command run.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
class DispatchableApplication extends Application
{
    private $dispatcher;

    public function setDispatcher(EventDispatcher $dispatcher)
    {
        $this->dispatcher = $dispatcher;
    }

    protected function doRunCommand(Command $command, InputInterface $input, OutputInterface $output)
    {
        if (null === $this->dispatcher) {
            return parent::doRunCommand($command, $input, $output);
        }

        $event = new ConsoleCommandEvent($command, $input, $output);
        $this->dispatcher->dispatch(ConsoleEvents::COMMAND, $event);

        $command = $event->getCommand();

        try {
            $exitCode = parent::doRunCommand($command, $input, $output);
        } catch (\Exception $e) {
            $event = new ConsoleTerminateEvent($command, $input, $output, $e->getCode());
            $this->dispatcher->dispatch(ConsoleEvents::TERMINATE, $event);

            $event = new ConsoleForExceptionEvent($command, $input, $output, $e, $event->getExitCode());
            $this->dispatcher->dispatch(ConsoleEvents::EXCEPTION, $event);

            throw $event->getException();
        }

        $event = new ConsoleTerminateEvent($command, $input, $output, $exitCode);
        $this->dispatcher->dispatch(ConsoleEvents::TERMINATE, $event);

        return $event->getExitCode();
    }
}
