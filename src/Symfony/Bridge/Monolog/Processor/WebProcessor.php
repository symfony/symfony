<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bridge\Monolog\Processor;

use Monolog\Processor\WebProcessor as BaseWebProcessor;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * WebProcessor override to read from the HttpFoundation's Request.
 *
 * @author Jordi Boggiano <j.boggiano@seld.be>
 *
 * @final
 */
class WebProcessor extends BaseWebProcessor implements EventSubscriberInterface
{
    public function __construct(array $extraFields = null)
    {
        // Pass an empty array as the default null value would access $_SERVER
        parent::__construct([], $extraFields);
    }

    public function onKernelRequest(RequestEvent $event)
    {
        if ($event->isMasterRequest()) {
            $this->serverData = $event->getRequest()->server->all();
            $this->serverData['REMOTE_ADDR'] = $event->getRequest()->getClientIp();
        }
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::REQUEST => ['onKernelRequest', 4096],
        ];
    }
}
