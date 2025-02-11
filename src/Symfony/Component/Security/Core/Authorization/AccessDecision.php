<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Security\Core\Authorization;

use Symfony\Component\Security\Core\Authorization\Voter\VoteInterface;

/**
 * An AccessDecision is returned by an AccessDecisionManager and contains the access verdict and all the related votes.
 *
 * @author Dany Maillard <danymaillard93b@gmail.com>
 * @author Roman JOLY <eltharin18@outlook.fr>
 */
final class AccessDecision
{
    /**
     * @param VoteInterface[]|int[] $votes
     */
    public function __construct(
        private readonly bool $access,
        private readonly array $votes = [],
        private readonly string $message = '',
    ) {
    }

    public function getAccess(): bool
    {
        return $this->access;
    }

    public function isGranted(): bool
    {
        return true === $this->access;
    }

    public function isDenied(): bool
    {
        return false === $this->access;
    }

    public function getMessage(): string
    {
        return $this->message;
    }

    /**
     * @return VoteInterface[]|int[]
     */
    public function getVotes(): array
    {
        return $this->votes;
    }
}
