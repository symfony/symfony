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
 * Affirmative is a StrategyInterface implementation where at least one item should be successful.
 *
 * @author Valentin Faugeroux <faugerouxvalentin@gmail.com>
 */
class AffirmativeStrategy implements StrategyInterface
{
    /**
     * {@inheritdoc}
     */
    public function isMet($numberOfSuccess, $numberOfItems)
    {
        return $numberOfSuccess >= 1;
    }

    /**
     * {@inheritdoc}
     */
    public function canBeMet($numberOfFailure, $numberOfItems)
    {
        return $numberOfFailure < $numberOfItems;
    }
}
