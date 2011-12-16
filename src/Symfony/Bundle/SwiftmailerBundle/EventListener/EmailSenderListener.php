<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\SwiftmailerBundle\EventListener;

use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpKernel\Event\PostResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Sends emails for the memory spool.
 *
 * Emails are sent on the kernel.terminate event.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
class EmailSenderListener implements EventSubscriberInterface
{
    private $container;

    public function __construct(ContainerInterface $container, $autoStart = false)
    {
        $this->container = $container;
    }

    public function onKernelTerminate(PostResponseEvent $event)
    {
        $transport = $this->container->get('mailer')->getTransport();
        if (!$transport instanceof \Swift_Transport_SpoolTransport) {
            return;
        }

        $spool = $transport->getSpool();
        if (!$spool instanceof \Swift_MemorySpool) {
            return;
        }

        $spool->flushQueue($this->container->get('swiftmailer.transport.real'));
    }

    static public function getSubscribedEvents()
    {
        return array(KernelEvents::TERMINATE => 'onKernelTerminate');
    }
}
