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
 * UnanimousStrategy is a StrategyInterface implementation where 100% of elements should be successful.
 *
 * @author Jérémy Derussé <jeremy@derusse.com>
 */
class UnanimousStrategy implements StrategyInterface
{
    /**
     * {@inheritdoc}
     */
    public function isMet(int $numberOfSuccess, int $numberOfItems)
    {
        return $numberOfSuccess === $numberOfItems;
    }

    /**
     * {@inheritdoc}
     */
    public function canBeMet(int $numberOfFailure, int $numberOfItems)
    {
        return 0 === $numberOfFailure;
    }
}
