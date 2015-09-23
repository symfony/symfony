<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\FrameworkBundle\EventListener;

use Symfony\Component\HttpFoundation\IpRetriever\IpRetrieverInterface;
use Symfony\Component\HttpKernel\Event\GetResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Sets the ip retriever in the request.
 *
 * This listener is only here to provide backward compatibility until 3.0
 *
 * @deprecated since version 2.7, to be removed in 3.0.
 *
 * @author Xavier Leune <xavier.leune@gmail.com>
 */
class IpRetrieverListener implements EventSubscriberInterface
{
    private $ipRetriever;

    public function __construct(IpRetrieverInterface $ipRetriever)
    {
        $this->ipRetriever = $ipRetriever;
    }

    public function onKernelRequest(GetResponseEvent $event)
    {
        if (!$event->isMasterRequest()) {
            return;
        }

        $event->getRequest()->setIpRetriever($this->ipRetriever);
    }

    public static function getSubscribedEvents()
    {
        return array(
            KernelEvents::REQUEST => 'onKernelRequest',
        );
    }
}
