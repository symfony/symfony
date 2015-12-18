<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Debug\Profiler;

use Symfony\Component\Console\ConsoleEvents;
use Symfony\Component\Debug\Exception\FlattenException;
use Symfony\Component\EventDispatcher\Event;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Profiler\DataCollector\DataCollectorInterface;

/**
 * ExceptionDataCollector.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 * @author Jelte Steijaert <jelte@khepri.be>
 */
class ExceptionDataCollector implements DataCollectorInterface, EventSubscriberInterface
{
    private $exception;

    /**
     * {@inheritdoc}
     */
    public function getCollectedData()
    {
        if (null === $this->exception) {
            return;
        }

        $exception = FlattenException::create($this->exception);

        return new ExceptionData($exception);
    }

    /**
     * Handles the onKernelTerminate event.
     *
     * @param Event $event
     */
    public function onException(Event $event)
    {
        if (method_exists($event, 'getException')) {
            $this->exception = $event->getException();
        }
    }

    /**
     * {@inheritdoc}
     */
    public static function getSubscribedEvents()
    {
        $events = array();

        if (defined('Symfony\Component\HttpKernel\KernelEvents::EXCEPTION')) {
            $events[KernelEvents::EXCEPTION] = array('onException');
        }
        if (defined('Symfony\Component\Console\ConsoleEvents::EXCEPTION')) {
            $events[ConsoleEvents::EXCEPTION] = array('onException');
        }

        return $events;
    }
}
