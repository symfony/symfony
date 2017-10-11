<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Console\EventListener;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Command\LockableTrait;
use Symfony\Component\Console\ConsoleEvents;
use Symfony\Component\Console\Event\ConsoleCommandEvent;
use Symfony\Component\Console\Event\ConsoleTerminateEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * This Subscriber uses LockableTrait and automatically handles locking and releasing commands.
 * No need to call "->lock()" and "->release()" explicitly.
 * All you need is to add LockableTrait to your command and add subscriber to event dispatcher.
 *
 * Optionally, you may add an option "wait-and-execute" to your command then you'll be able to perform blocking lock.
 *
 * @example
 * $this->addOption(
 *     'wait-and-execute',
 *     null,
 *     InputOption::VALUE_NONE,
 *     'Wait for running process to complete and execute command'
 * )
 *
 *
 * @author Maksym Slesarenko <maks.slesarenko@gmail.com>
 */
class LockableSubscriber implements EventSubscriberInterface
{
    public static function getSubscribedEvents()
    {
        return [
            ConsoleEvents::COMMAND => 'onStart',
            ConsoleEvents::TERMINATE => 'onComplete',
        ];
    }

    public function onStart(ConsoleCommandEvent $event)
    {
        $command = $event->getCommand();
        if ($this->isLockableCommand($command)) {
            /* @var LockableTrait|Command $command */
            $input = $event->getInput();
            $blocking = $input->hasOption('wait-and-execute') ? $input->getOption('wait-and-execute') : false;

            if (!$command->lock($command->getName(), $blocking)) {
                $event->getOutput()->writeln('<comment>The command is already running in another process.</comment>');
                $event->disableCommand();
            }
        }
    }

    public function onComplete(ConsoleTerminateEvent $event)
    {
        $command = $event->getCommand();
        if ($this->isLockableCommand($command)) {
            /* @var LockableTrait|Command $command */
            $command->release();
        }
    }

    protected function isLockableCommand(Command $command)
    {
        return in_array(LockableTrait::class, class_uses($command));
    }
}
