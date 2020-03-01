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
use Symfony\Component\Security\Core\Exception\InvalidCsrfTokenException;
use Symfony\Component\Security\Csrf\CsrfToken;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;
use Symfony\Component\Security\Http\Authenticator\CsrfProtectedAuthenticatorInterface;
use Symfony\Component\Security\Http\Event\VerifyAuthenticatorCredentialsEvent;

class CsrfProtectionListener implements EventSubscriberInterface
{
    private $csrfTokenManager;

    public function __construct(CsrfTokenManagerInterface $csrfTokenManager)
    {
        $this->csrfTokenManager = $csrfTokenManager;
    }

    public function verifyCredentials(VerifyAuthenticatorCredentialsEvent $event): void
    {
        $authenticator = $event->getAuthenticator();
        if (!$authenticator instanceof CsrfProtectedAuthenticatorInterface) {
            return;
        }

        $csrfTokenValue = $authenticator->getCsrfToken($event->getCredentials());
        if (null === $csrfTokenValue) {
            return;
        }

        $csrfToken = new CsrfToken($authenticator->getCsrfTokenId(), $csrfTokenValue);
        if (false === $this->csrfTokenManager->isTokenValid($csrfToken)) {
            throw new InvalidCsrfTokenException('Invalid CSRF token.');
        }
    }

    public static function getSubscribedEvents(): array
    {
        return [VerifyAuthenticatorCredentialsEvent::class => ['verifyCredentials', 256]];
    }
}
