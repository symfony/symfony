<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Security\Core\Tests\Fixtures;

use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\VoteInterface;
use Symfony\Component\Security\Core\Authorization\Voter\VoterInterface;

final class DummyVoterWithObject implements VoterInterface
{
    public function __construct(private ?VoteInterface $vote = null)
    {
    }

    public function vote(TokenInterface $token, $subject, array $attributes, ?VoteInterface &$vote = null): int
    {
        $vote = $this->vote;
        return $vote->getAccess();
    }
}
