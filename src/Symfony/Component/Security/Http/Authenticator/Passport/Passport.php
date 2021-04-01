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
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Credentials\CredentialsInterface;

/**
 * The default implementation for passports.
 *
 * @author Wouter de Jong <wouter@wouterj.nl>
 *
 * @experimental in 5.2
 */
class Passport implements UserPassportInterface
{
    use PassportTrait;

    protected $user;

    private $attributes = [];

    /**
     * @param UserBadge            $userBadge
     * @param CredentialsInterface $credentials the credentials to check for this authentication, use
     *                                          SelfValidatingPassport if no credentials should be checked
     * @param BadgeInterface[]     $badges
     */
    public function __construct($userBadge, CredentialsInterface $credentials, array $badges = [])
    {
        if ($userBadge instanceof UserInterface) {
            trigger_deprecation('symfony/security-http', '5.2', 'The 1st argument of "%s" must be an instance of "%s", support for "%s" will be removed in symfony/security-http 5.3.', __CLASS__, UserBadge::class, UserInterface::class);

            $this->user = $userBadge;
        } elseif ($userBadge instanceof UserBadge) {
            $this->addBadge($userBadge);
        } else {
            throw new \TypeError(sprintf('Argument 1 of "%s" must be an instance of "%s", "%s" given.', __METHOD__, UserBadge::class, get_debug_type($userBadge)));
        }

        $this->addBadge($credentials);
        foreach ($badges as $badge) {
            $this->addBadge($badge);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function getUser(): UserInterface
    {
        if (null === $this->user) {
            if (!$this->hasBadge(UserBadge::class)) {
                throw new \LogicException('Cannot get the Security user, no username or UserBadge configured for this passport.');
            }

            $this->user = $this->getBadge(UserBadge::class)->getUser();
        }

        return $this->user;
    }

    /**
     * @param mixed $value
     */
    public function setAttribute(string $name, $value): void
    {
        $this->attributes[$name] = $value;
    }

    /**
     * @param mixed $default
     *
     * @return mixed
     */
    public function getAttribute(string $name, $default = null)
    {
        return $this->attributes[$name] ?? $default;
    }
}
