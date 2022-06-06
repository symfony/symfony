<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Security\Http\Event;

use Symfony\Component\Security\Http\Authenticator\AuthenticatorInterface;
use Symfony\Component\Security\Http\Authenticator\Passport\Passport;
use Symfony\Component\Security\Http\Authenticator\Passport\PassportInterface;
use Symfony\Contracts\EventDispatcher\Event;

/**
 * This event is dispatched when the credentials have to be checked.
 *
 * Listeners to this event must validate the user and the
 * credentials (e.g. default listeners do password verification and
 * user checking)
 *
 * @author Wouter de Jong <wouter@wouterj.nl>
 */
class CheckPassportEvent extends Event
{
    private $authenticator;
    private $passport;

    /**
     * @param Passport $passport
     */
    public function __construct(AuthenticatorInterface $authenticator, PassportInterface $passport)
    {
        if (!$passport instanceof Passport) {
            trigger_deprecation('symfony/security-http', '5.4', 'Not passing an instance of "%s" as "$passport" argument of "%s()" is deprecated, "%s" given.', Passport::class, __METHOD__, get_debug_type($passport));
        }

        $this->authenticator = $authenticator;
        $this->passport = $passport;
    }

    public function getAuthenticator(): AuthenticatorInterface
    {
        return $this->authenticator;
    }

    public function getPassport(): PassportInterface
    {
        return $this->passport;
    }
}
