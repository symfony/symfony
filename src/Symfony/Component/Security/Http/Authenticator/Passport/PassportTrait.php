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

use Symfony\Component\Security\Core\Exception\BadCredentialsException;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\BadgeInterface;

/**
 * @author Wouter de Jong <wouter@wouterj.nl>
 *
 * @experimental in 5.2
 */
trait PassportTrait
{
    /**
     * @var BadgeInterface[]
     */
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

    public function checkIfCompletelyResolved(): void
    {
        foreach ($this->badges as $badge) {
            if (!$badge->isResolved()) {
                throw new BadCredentialsException(sprintf('Authentication failed security badge "%s" is not resolved, did you forget to register the correct listeners?', \get_class($badge)));
            }
        }
    }
}
