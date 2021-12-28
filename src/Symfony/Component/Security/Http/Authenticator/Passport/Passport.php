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
 */
class Passport implements UserPassportInterface
{
    protected $user;

    private $badges = [];
    private $attributes = [];

    /**
     * @param CredentialsInterface $credentials the credentials to check for this authentication, use
     *                                          SelfValidatingPassport if no credentials should be checked
     * @param BadgeInterface[]     $badges
     */
    public function __construct(UserBadge $userBadge, CredentialsInterface $credentials, array $badges = [])
    {
        $this->addBadge($userBadge);
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
     * @return $this
     */
    public function addBadge(BadgeInterface $badge): PassportInterface
    {
        $this->badges[\get_class($badge)] = $badge;

        return $this;
    }

    public function hasBadge(string $badgeFqcn): bool
    {
        return isset($this->badges[$badgeFqcn]);
    }

    public function getBadge(string $badgeFqcn): ?BadgeInterface
    {
        return $this->badges[$badgeFqcn] ?? null;
    }

    /**
     * @return array<class-string<BadgeInterface>, BadgeInterface>
     */
    public function getBadges(): array
    {
        return $this->badges;
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

    public function getAttributes(): array
    {
        return $this->attributes;
    }
}
