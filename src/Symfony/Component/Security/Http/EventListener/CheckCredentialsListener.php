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
use Symfony\Component\PasswordHasher\Hasher\PasswordHasherFactoryInterface;
use Symfony\Component\Security\Core\Encoder\EncoderFactoryInterface;
use Symfony\Component\Security\Core\Exception\BadCredentialsException;
use Symfony\Component\Security\Core\User\LegacyPasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\PasswordUpgradeBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Credentials\CustomCredentials;
use Symfony\Component\Security\Http\Authenticator\Passport\Credentials\PasswordCredentials;
use Symfony\Component\Security\Http\Authenticator\Passport\UserPassportInterface;
use Symfony\Component\Security\Http\Event\CheckPassportEvent;

/**
 * This listeners uses the interfaces of authenticators to
 * determine how to check credentials.
 *
 * @author Wouter de Jong <wouter@driveamber.com>
 *
 * @final
 */
class CheckCredentialsListener implements EventSubscriberInterface
{
    private $hasherFactory;

    /**
     * @param PasswordHasherFactoryInterface $hasherFactory
     */
    public function __construct($hasherFactory)
    {
        if ($hasherFactory instanceof EncoderFactoryInterface) {
            trigger_deprecation('symfony/security-core', '5.3', 'Passing a "%s" instance to the "%s" constructor is deprecated, use "%s" instead.', EncoderFactoryInterface::class, __CLASS__, PasswordHasherFactoryInterface::class);
        }

        $this->hasherFactory = $hasherFactory;
    }

    public function checkPassport(CheckPassportEvent $event): void
    {
        $passport = $event->getPassport();
        if ($passport instanceof UserPassportInterface && $passport->hasBadge(PasswordCredentials::class)) {
            // Use the password hasher to validate the credentials
            $user = $passport->getUser();

            if (!$user instanceof PasswordAuthenticatedUserInterface) {
                trigger_deprecation('symfony/security-http', '5.3', 'Not implementing the "%s" interface in class "%s" while using password-based authentication is deprecated.', PasswordAuthenticatedUserInterface::class, get_debug_type($user));
            }

            /** @var PasswordCredentials $badge */
            $badge = $passport->getBadge(PasswordCredentials::class);

            if ($badge->isResolved()) {
                return;
            }

            $presentedPassword = $badge->getPassword();
            if ('' === $presentedPassword) {
                throw new BadCredentialsException('The presented password cannot be empty.');
            }

            if (null === $user->getPassword()) {
                throw new BadCredentialsException('The presented password is invalid.');
            }

            $salt = $user->getSalt();
            if ($salt && !$user instanceof LegacyPasswordAuthenticatedUserInterface) {
                trigger_deprecation('symfony/security-http', '5.3', 'Returning a string from "getSalt()" without implementing the "%s" interface is deprecated, the "%s" class should implement it.', LegacyPasswordAuthenticatedUserInterface::class, get_debug_type($user));
            }

            // @deprecated since Symfony 5.3
            if ($this->hasherFactory instanceof EncoderFactoryInterface) {
                if (!$this->hasherFactory->getEncoder($user)->isPasswordValid($user->getPassword(), $presentedPassword, $salt)) {
                    throw new BadCredentialsException('The presented password is invalid.');
                }
            } else {
                if (!$this->hasherFactory->getPasswordHasher($user)->verify($user->getPassword(), $presentedPassword, $salt)) {
                    throw new BadCredentialsException('The presented password is invalid.');
                }
            }

            $badge->markResolved();

            if (!$passport->hasBadge(PasswordUpgradeBadge::class)) {
                $passport->addBadge(new PasswordUpgradeBadge($presentedPassword));
            }

            return;
        }

        if ($passport->hasBadge(CustomCredentials::class)) {
            /** @var CustomCredentials $badge */
            $badge = $passport->getBadge(CustomCredentials::class);
            if ($badge->isResolved()) {
                return;
            }

            $badge->executeCustomChecker($passport->getUser());

            return;
        }
    }

    public static function getSubscribedEvents(): array
    {
        return [CheckPassportEvent::class => 'checkPassport'];
    }
}
