<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Notifier\DataCollector;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\DataCollector\DataCollector;
use Symfony\Component\Notifier\Event\NotificationEvents;
use Symfony\Component\Notifier\EventListener\NotificationLoggerListener;

/**
 * @author Fabien Potencier <fabien@symfony.com>
 */
final class NotificationDataCollector extends DataCollector
{
    private NotificationLoggerListener $logger;

    public function __construct(NotificationLoggerListener $logger)
    {
        $this->logger = $logger;
    }

    public function collect(Request $request, Response $response, \Throwable $exception = null): void
    {
        $this->data['events'] = $this->logger->getEvents();
    }

    public function getEvents(): NotificationEvents
    {
        return $this->data['events'];
    }

    public function reset(): void
    {
        $this->data = [];
    }

    public function getName(): string
    {
        return 'notifier';
    }
}
