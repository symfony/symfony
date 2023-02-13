<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Security\Http\EventListener;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Security\Csrf\TokenStorage\ClearableTokenStorageInterface;
use Symfony\Component\Security\Http\Session\SessionAuthenticationStrategyInterface;

/**
 * Clears the CSRF token storage after a successful login has migrated the session.
 * We wait for the kernel.response event because CSRF tokens could need to be checked after authentication.
 *
 * @author Mathieu Lechat <mathieu.lechat@les-tilleuls.com>
 */
final class CsrfTokenClearingLoginListener implements EventSubscriberInterface
{
    /** @var ClearableTokenStorageInterface */
    private $csrfTokenStorage;

    public function __construct(ClearableTokenStorageInterface $csrfTokenStorage)
    {
        $this->csrfTokenStorage = $csrfTokenStorage;
    }

    public function onKernelResponse(ResponseEvent $event): void
    {
        if (!$event->isMasterRequest()) {
            return;
        }

        if ($event->getRequest()->attributes->get(SessionAuthenticationStrategyInterface::CLEAR_CSRF_STORAGE_ATTR_NAME, false)) {
            $this->csrfTokenStorage->clear();
        }
    }

    public static function getSubscribedEvents(): array
    {
        return [KernelEvents::RESPONSE => 'onKernelResponse'];
    }
}
