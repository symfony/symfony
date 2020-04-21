<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Security\Http\Authenticator\Passport;

use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\BadgeInterface;
use Symfony\Component\Security\Http\Authenticator\Passport\Credentials\CredentialsInterface;

/**
 * The default implementation for passports.
 *
 * @author Wouter de Jong <wouter@wouterj.nl>
 *
 * @experimental in 5.1
 */
class Passport implements UserPassportInterface
{
    use PassportTrait;

    protected $user;

    /**
     * @param CredentialsInterface $credentials the credentials to check for this authentication, use
     *                                          SelfValidatingPassport if no credentials should be checked.
     * @param BadgeInterface[]     $badges
     */
    public function __construct(UserInterface $user, CredentialsInterface $credentials, array $badges = [])
    {
        $this->user = $user;

        $this->addBadge($credentials);
        foreach ($badges as $badge) {
            $this->addBadge($badge);
        }
    }

    public function getUser(): UserInterface
    {
        return $this->user;
    }
}
