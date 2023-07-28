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
 * A Passport contains all security-related information that needs to be
 * validated during authentication.
 *
 * A passport badge can be used to add any additional information to the
 * passport.
 *
 * @author Wouter de Jong <wouter@wouterj.nl>
 */
class Passport
{
    protected UserInterface $user;

    private array $badges = [];
    private array $attributes = [];

    /**
     * @param CredentialsInterface $credentials The credentials to check for this authentication, use
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

    public function getUser(): UserInterface
    {
        if (!isset($this->user)) {
            if (!$this->hasBadge(UserBadge::class)) {
                throw new \LogicException('Cannot get the Security user, no username or UserBadge configured for this passport.');
            }

            $this->user = $this->getBadge(UserBadge::class)->getUser();
        }

        return $this->user;
    }

    /**
     * Adds a new security badge.
     *
     * A passport can hold only one instance of the same security badge.
     * This method replaces the current badge if it is already set on this
     * passport.
     *
     * @param string|null $badgeFqcn A FQCN to which the badge should be mapped to.
     *                               This allows replacing a built-in badge by a custom one using
     *                               e.g. addBadge(new MyCustomUserBadge(), UserBadge::class)
     *
     * @return $this
     */
    public function addBadge(BadgeInterface $badge, string $badgeFqcn = null): static
    {
        $badgeFqcn ??= $badge::class;

        $this->badges[$badgeFqcn] = $badge;

        return $this;
    }

    public function hasBadge(string $badgeFqcn): bool
    {
        return isset($this->badges[$badgeFqcn]);
    }

    /**
     * @template TBadge of BadgeInterface
     *
     * @param class-string<TBadge> $badgeFqcn
     *
     * @return TBadge|null
     */
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

    public function setAttribute(string $name, mixed $value): void
    {
        $this->attributes[$name] = $value;
    }

    public function getAttribute(string $name, mixed $default = null): mixed
    {
        return $this->attributes[$name] ?? $default;
    }

    public function getAttributes(): array
    {
        return $this->attributes;
    }
}
