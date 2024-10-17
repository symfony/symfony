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

/**
 * A strategy for turning a stream of votes into a final decision.
 *
 * @author Alexander M. Turek <me@derrabus.de>
 *
 * @method AccessDecision getDecision(\Traversable $votes)
 */
interface AccessDecisionStrategyInterface
{
    /**
     * @param \Traversable<int> $results
     */
    public function decide(\Traversable $results): bool;
}
