<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Lock\Strategy;

/**
 * StrategyInterface defines an interface to indicate when a quorum is met and can be met.
 *
 * @author Jérémy Derussé <jeremy@derusse.com>
 */
interface StrategyInterface
{
    /**
     * Returns whether or not the quorum is met.
     */
    public function isMet(int $numberOfSuccess, int $numberOfItems): bool;

    /**
     * Returns whether or not the quorum *could* be met.
     *
     * This method does not mean the quorum *would* be met for sure, but can be useful to stop a process early when you
     * known there is no chance to meet the quorum.
     */
    public function canBeMet(int $numberOfFailure, int $numberOfItems): bool;
}
