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
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\CsrfTokenBadge;
use Symfony\Component\Security\Http\Event\CheckPassportEvent;

/**
 * @author Wouter de Jong <wouter@wouterj.nl>
 *
 * @final
 */
class CsrfProtectionListener implements EventSubscriberInterface
{
    public function __construct(
        private CsrfTokenManagerInterface $csrfTokenManager,
    ) {
    }

    public function checkPassport(CheckPassportEvent $event): void
    {
        $passport = $event->getPassport();
        if (!$passport->hasBadge(CsrfTokenBadge::class)) {
            return;
        }

        /** @var CsrfTokenBadge $badge */
        $badge = $passport->getBadge(CsrfTokenBadge::class);
        if ($badge->isResolved()) {
            return;
        }

        $csrfToken = new CsrfToken($badge->getCsrfTokenId(), $badge->getCsrfToken());

        if (false === $this->csrfTokenManager->isTokenValid($csrfToken)) {
            throw new InvalidCsrfTokenException('Invalid CSRF token.');
        }

        $badge->markResolved();
    }

    public static function getSubscribedEvents(): array
    {
        return [CheckPassportEvent::class => ['checkPassport', 512]];
    }
}
