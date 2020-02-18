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

trait AccessTrait
{
    /** @var int */
    protected $access;

    public function getAccess(): int
    {
        return $this->access;
    }

    public function isGranted(): bool
    {
        return VoterInterface::ACCESS_GRANTED === $this->access;
    }

    public function isAbstain(): bool
    {
        return VoterInterface::ACCESS_ABSTAIN === $this->access;
    }

    public function isDenied(): bool
    {
        return VoterInterface::ACCESS_DENIED === $this->access;
    }
}
