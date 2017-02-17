<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Lock\Quorum;

use Symfony\Component\Lock\QuorumInterface;

/**
 * UnanimousStrategy is a QuorumInterface implementation where 100% of elements should be successful.
 *
 * @author Jérémy Derussé <jeremy@derusse.com>
 */
class UnanimousStrategy implements QuorumInterface
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
        return $numberOfFailure === 0;
    }
}
