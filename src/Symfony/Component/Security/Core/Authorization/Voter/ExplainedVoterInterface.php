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

interface ExplainedVoterInterface
{
    public function grant(string $reason = '', array $parameters = []): Vote;

    public function abstain(string $reason = '', array $parameters = []): Vote;

    public function deny(string $reason = '', array $parameters = []): Vote;
}
