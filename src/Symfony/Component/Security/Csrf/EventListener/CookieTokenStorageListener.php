<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Security\Csrf\EventListener;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Security\Csrf\TokenStorage\CookieTokenStorage;

/**
 * Inject transient cookies in the response.
 *
 * @author Oliver Hoff <oliver@hofff.com>
 * @author Jérémy Derussé <jeremy@derusse.com>
 */
class CookieTokenStorageListener implements EventSubscriberInterface
{
    private $cookieTokenStorage;

    public function __construct(CookieTokenStorage $cookieTokenStorage)
    {
        $this->cookieTokenStorage = $cookieTokenStorage;
    }

    public function onKernelResponse(ResponseEvent $event)
    {
        $this->cookieTokenStorage->sendCookies($event->getResponse());
    }

    /**
     * {@inheritdoc}
     */
    public static function getSubscribedEvents()
    {
        return [
            KernelEvents::RESPONSE => 'onKernelResponse',
        ];
    }
}
