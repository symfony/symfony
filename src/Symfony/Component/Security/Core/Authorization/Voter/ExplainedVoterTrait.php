<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Security\Core\Authorization\Voter;

trait ExplainedVoterTrait
{
    public function grant(string $reason = '', array $parameters = []): Vote
    {
        return Vote::createGranted($reason, $parameters);
    }

    public function abstain(string $reason = '', array $parameters = []): Vote
    {
        return Vote::createAbstrain($reason, $parameters);
    }

    public function deny(string $reason = '', array $parameters = []): Vote
    {
        return Vote::createDenied($reason, $parameters);
    }
}
