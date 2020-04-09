<?php

namespace Symfony\Component\Security\Http\EventListener;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Security\Core\User\UserCheckerInterface;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\PreAuthenticatedUserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\UserPassportInterface;
use Symfony\Component\Security\Http\Event\LoginSuccessEvent;
use Symfony\Component\Security\Http\Event\VerifyAuthenticatorCredentialsEvent;

/**
 * @author Wouter de Jong <wouter@wouterj.nl>
 *
 * @final
 * @experimental in 5.1
 */
class UserCheckerListener implements EventSubscriberInterface
{
    private $userChecker;

    public function __construct(UserCheckerInterface $userChecker)
    {
        $this->userChecker = $userChecker;
    }

    public function preCredentialsVerification(VerifyAuthenticatorCredentialsEvent $event): void
    {
        $passport = $event->getPassport();
        if (!$passport instanceof UserPassportInterface || $passport->hasBadge(PreAuthenticatedUserBadge::class)) {
            return;
        }

        $this->userChecker->checkPreAuth($passport->getUser());
    }

    public function postCredentialsVerification(LoginSuccessEvent $event): void
    {
        $passport = $event->getPassport();
        if (!$passport instanceof UserPassportInterface || null === $passport->getUser()) {
            return;
        }

        $this->userChecker->checkPostAuth($passport->getUser());
    }

    public static function getSubscribedEvents(): array
    {
        return [
            VerifyAuthenticatorCredentialsEvent::class => [['preCredentialsVerification', 256]],
            LoginSuccessEvent::class => ['postCredentialsVerification', 256],
        ];
    }
}
