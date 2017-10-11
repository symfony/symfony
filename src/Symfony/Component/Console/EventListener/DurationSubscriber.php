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

use Symfony\Component\Console\ConsoleEvents;
use Symfony\Component\Console\Event\ConsoleCommandEvent;
use Symfony\Component\Console\Event\ConsoleTerminateEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * This Subscriber outputs total amount of seconds that took command to complete
 *
 * Now can start command execution and go for a cup of coffee, when you back you will see how long it took
 * for command to complete.
 *
 * @author Maksym Slesarenko <maks.slesarenko@gmail.com>
 */
class DurationSubscriber implements EventSubscriberInterface
{
    /**
     * @var integer
     */
    private $startedTime;

    public static function getSubscribedEvents()
    {
        return array(
            ConsoleEvents::COMMAND => 'onStart',
            ConsoleEvents::TERMINATE => 'onComplete',
        );
    }

    public function onStart(ConsoleCommandEvent $event)
    {
        $this->startedTime = time();
    }

    public function onComplete(ConsoleTerminateEvent $event)
    {
        $duration = time() - $this->startedTime;

        $command = $event->getCommand()->getName();

        $event->getOutput()->writeln(sprintf('<info>%s</info> completed in <info>%s</info>s', $command, $duration));
    }
}
