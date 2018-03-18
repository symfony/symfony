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

use Symfony\Bundle\FrameworkBundle\Controller\RedirectController;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\GetResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Resolve the controller for requests containing the `_redirect_to` attributes.
 *
 * @author Samuel Roze <samuel.roze@gmail.com>
 */
class ResolveRedirectControllerSubscriber implements EventSubscriberInterface
{
    public static function getSubscribedEvents()
    {
        return array(
            KernelEvents::REQUEST => array('onKernelRequest', 20),
        );
    }

    public function onKernelRequest(GetResponseEvent $event)
    {
        $requestAttributes = $event->getRequest()->attributes;

        if (!$requestAttributes->has('_controller') && $redirectTo = $requestAttributes->get('_redirect_to')) {
            if ($this->looksLikeUrl($redirectTo)) {
                $requestAttributes->set('_controller', array(RedirectController::class, 'urlRedirectAction'));
                $requestAttributes->set('path', $redirectTo);
            } else {
                $requestAttributes->set('_controller', array(RedirectController::class, 'redirectAction'));
                $requestAttributes->set('route', $redirectTo);
            }

            if (!$requestAttributes->has('permanent')) {
                $requestAttributes->set('permanent', $requestAttributes->get('_redirect_permanent', false));
            }
        }
    }

    private function looksLikeUrl(string $urlOrRouteName): bool
    {
        foreach (array('/', 'http://', 'https://') as $pattern) {
            if (0 === strpos($urlOrRouteName, $pattern)) {
                return true;
            }
        }

        return false;
    }
}
