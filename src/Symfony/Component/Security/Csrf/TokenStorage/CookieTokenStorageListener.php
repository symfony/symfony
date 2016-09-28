<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Security\Csrf\TokenStorage;

use Symfony\Component\HttpKernel\Event\FilterResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Checks the request's attributes for a CookieTokenStorage instance. If one is
 * found, the cookies representing the storage's changeset are appended to the
 * response headers.
 *
 * TODO where to put this class?
 *
 * @author Oliver Hoff <oliver@hofff.com>
 */
class CookieTokenStorageListener implements EventSubscriberInterface
{
    /**
     * @var string
     */
    const DEFAULT_TOKEN_STORAGE_KEY = '_csrf_token_storage';

    /**
     * @var string
     */
    private $tokenStorageKey;

    /**
     * @param string|null $tokenStorageKey
     */
    public function __construct($tokenStorageKey = null)
    {
        $this->tokenStorageKey = null === $tokenStorageKey ? self::DEFAULT_TOKEN_STORAGE_KEY : $tokenStorageKey;
    }

    /**
     * @param FilterResponseEvent $event
     */
    public function onKernelResponse(FilterResponseEvent $event)
    {
        $storage = $event->getRequest()->attributes->get($this->tokenStorageKey);
        if (!$storage instanceof CookieTokenStorage) {
            return;
        }

        $headers = $event->getResponse()->headers;
        foreach ($storage->createCookies() as $cookie) {
            $headers->setCookie($cookie);
        }
    }

    /**
     * {@inheritdoc}
     */
    public static function getSubscribedEvents()
    {
        return array(
            KernelEvents::RESPONSE => array(array('onKernelResponse', 0)),
        );
    }
}
