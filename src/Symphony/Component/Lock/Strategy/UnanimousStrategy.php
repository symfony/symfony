<?php

/*
 * This file is part of the Symphony package.
 *
 * (c) Fabien Potencier <fabien@symphony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symphony\Component\Lock\Strategy;

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
    public function isMet($numberOfSuccess, $numberOfItems)
    {
        return $numberOfSuccess === $numberOfItems;
    }

    /**
     * {@inheritdoc}
     */
    public function canBeMet($numberOfFailure, $numberOfItems)
    {
        return 0 === $numberOfFailure;
    }
}
