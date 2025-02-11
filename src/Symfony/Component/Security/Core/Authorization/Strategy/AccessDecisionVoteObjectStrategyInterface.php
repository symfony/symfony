<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Security\Core\Authorization\Strategy;

use Symfony\Component\Security\Core\Authorization\AccessDecision;
use Symfony\Component\Security\Core\Authorization\Voter\VoteInterface;

/**
 * A strategy for turning a stream of votes into a final decision.
 *
 * @author Alexander M. Turek <me@derrabus.de>
 */
interface AccessDecisionVoteObjectStrategyInterface extends AccessDecisionStrategyInterface
{
    /**
     * @param \Traversable<int|VoteInterface> $results
     */
    public function decide(\Traversable $results, ?AccessDecision &$accessDecision = null): bool;
}
