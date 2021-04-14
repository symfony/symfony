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

use Symfony\Component\Security\Http\Authenticator\Passport\Badge\BadgeInterface;

/**
 * @author Wouter de Jong <wouter@wouterj.nl>
 */
trait PassportTrait
{
    private $badges = [];

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
}
