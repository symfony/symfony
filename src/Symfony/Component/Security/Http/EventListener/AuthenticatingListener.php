<?php

namespace Symfony\Component\Security\Http\EventListener;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Security\Core\Encoder\EncoderFactoryInterface;
use Symfony\Component\Security\Core\Exception\LogicException;
use Symfony\Component\Security\Http\Authenticator\CustomAuthenticatedInterface;
use Symfony\Component\Security\Http\Authenticator\PasswordAuthenticatedInterface;
use Symfony\Component\Security\Http\Authenticator\TokenAuthenticatedInterface;
use Symfony\Component\Security\Http\Event\VerifyAuthenticatorCredentialsEvent;

/**
 * This listeners uses the interfaces of authenticators to
 * determine how to check credentials.
 *
 * @author Wouter de Jong <wouter@driveamber.com>
 *
 * @final
 * @experimental in 5.1
 */
class AuthenticatingListener implements EventSubscriberInterface
{
    private $encoderFactory;

    public function __construct(EncoderFactoryInterface $encoderFactory)
    {
        $this->encoderFactory = $encoderFactory;
    }

    public static function getSubscribedEvents(): array
    {
        return [VerifyAuthenticatorCredentialsEvent::class => ['onAuthenticating', 128]];
    }

    public function onAuthenticating(VerifyAuthenticatorCredentialsEvent $event): void
    {
        $authenticator = $event->getAuthenticator();
        if ($authenticator instanceof PasswordAuthenticatedInterface) {
            // Use the password encoder to validate the credentials
            $user = $event->getUser();
            $event->setCredentialsValid($this->encoderFactory->getEncoder($user)->isPasswordValid(
                $user->getPassword(),
                $authenticator->getPassword($event->getPreAuthenticatedToken()->getCredentials()),
                $user->getSalt()
            ));

            return;
        }

        if ($authenticator instanceof TokenAuthenticatedInterface) {
            if (null !== $authenticator->getToken($event->getCredentials())) {
                // Token based authenticators do not have a credential validation step
                $event->setCredentialsValid();
            }

            return;
        }

        if ($authenticator instanceof CustomAuthenticatedInterface) {
            $event->setCredentialsValid($authenticator->checkCredentials($event->getPreAuthenticatedToken()->getCredentials(), $event->getUser()));

            return;
        }

        throw new LogicException(sprintf('Authenticator %s does not have valid credentials. Authenticators must implement one of the authenticated interfaces (%s, %s or %s).', \get_class($authenticator), PasswordAuthenticatedInterface::class, TokenAuthenticatedInterface::class, CustomAuthenticatedInterface::class));
    }
}
