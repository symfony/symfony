<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\FrameworkBundle\Test;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * This event subscriber allows you to inspect all exceptions thrown by the application.
 * This is useful since the HttpKernel catches all throwables and turns them into
 * an HTTP response.
 *
 * This class should only be used in tests.
 *
 * @author Tobias Nyholm <tobias.nyholm@gmail.com>
 */
class ExceptionSubscriber implements EventSubscriberInterface
{
    /**
     * @var list<\Throwable>
     */
    private static array $exceptions = [];

    public function onKernelException(ExceptionEvent $event)
    {
        self::$exceptions[] = $event->getThrowable();
    }

    /**
     * @return list<\Throwable>
     */
    public static function shiftAll(): array
    {
        $exceptions = self::$exceptions;
        self::$exceptions = [];

        return $exceptions;
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::EXCEPTION => 'onKernelException',
        ];
    }
}
