<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\SecurityBundle\Tests\DependencyInjection\Fixtures\Voter;

use Symfony\Bundle\SecurityBundle\Attribute\AsVoter;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Vote;
use Symfony\Component\Security\Core\Authorization\Voter\VoterInterface;

#[AsVoter(priority: 200)]
final class AsVoterHighPriority implements VoterInterface
{
    public function vote(TokenInterface $token, $subject, array $attributes, ?Vote $vote = null): int
    {
    }
}
