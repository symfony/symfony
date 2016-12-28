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
 * MajorityQuorum is a QuorumInterface implementation where strictly more than 50% items should be successful.
 *
 * @author Jérémy Derussé <jeremy@derusse.com>
 */
class MajorityQuorum implements QuorumInterface
{
    /**
     * {@inheritdoc}
     */
    public function isMet($numberOfSuccess, $numberOfItems)
    {
        return $numberOfSuccess > ($numberOfItems / 2);
    }

    /**
     * {@inheritdoc}
     */
    public function canBeMet($numberOfFailure, $numberOfItems)
    {
        return $numberOfFailure < ($numberOfItems / 2);
    }
}
