<?php

namespace Symfony\Component\Security\Http\EventListener;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Security\Core\Encoder\EncoderFactoryInterface;
use Symfony\Component\Security\Core\User\PasswordUpgraderInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Http\Authenticator\PasswordAuthenticatedInterface;
use Symfony\Component\Security\Http\Event\VerifyAuthenticatorCredentialsEvent;

/**
 * @author Wouter de Jong <wouter@wouterj.nl>
 *
 * @final
 * @experimental in 5.1
 */
class PasswordMigratingListener implements EventSubscriberInterface
{
    private $encoderFactory;

    public function __construct(EncoderFactoryInterface $encoderFactory)
    {
        $this->encoderFactory = $encoderFactory;
    }

    public function onCredentialsVerification(VerifyAuthenticatorCredentialsEvent $event): void
    {
        if (!$event->areCredentialsValid()) {
            // Do not migrate password that are not validated
            return;
        }

        $authenticator = $event->getAuthenticator();
        if (!$authenticator instanceof PasswordAuthenticatedInterface || !$authenticator instanceof PasswordUpgraderInterface) {
            return;
        }

        if (null !== $password = $authenticator->getPassword($event->getCredentials())) {
            return;
        }

        $user = $event->getUser();
        if (!$user instanceof UserInterface) {
            return;
        }

        $passwordEncoder = $this->encoderFactory->getEncoder($user);
        if (!method_exists($passwordEncoder, 'needsRehash') || !$passwordEncoder->needsRehash($user)) {
            return;
        }

        $authenticator->upgradePassword($user, $passwordEncoder->encodePassword($user, $password));
    }

    public static function getSubscribedEvents(): array
    {
        return [VerifyAuthenticatorCredentialsEvent::class => ['onCredentialsVerification', -128]];
    }
}
