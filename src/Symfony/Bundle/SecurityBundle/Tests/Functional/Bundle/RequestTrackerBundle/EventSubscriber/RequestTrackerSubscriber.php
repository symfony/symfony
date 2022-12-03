<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\SecurityBundle\Tests\Functional\Bundle\RequestTrackerBundle\EventSubscriber;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\RequestEvent;

final class RequestTrackerSubscriber implements EventSubscriberInterface
{
    private ?Request $lastRequest;

    public static function getSubscribedEvents(): array
    {
        return [
            RequestEvent::class => 'onRequest',
        ];
    }

    public function onRequest(RequestEvent $event)
    {
        $this->lastRequest = $event->getRequest();
    }

    public function getLastRequest(): ?Request
    {
        return $this->lastRequest;
    }
}
