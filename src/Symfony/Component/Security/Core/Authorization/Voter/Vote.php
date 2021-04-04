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

/**
 * A Vote is returned by a Voter and contains the access (granted, abstain or denied).
 * It can also contains a reason explaining the vote decision.
 *
 * @author Dany Maillard <danymaillard93b@gmail.com>
 */
final class Vote
{
    use AccessTrait;

    private $reason;
    private $parameters;

    /**
     * @param int $access One of the VoterInterface::ACCESS_* constants
     */
    private function __construct(int $access, string $reason = '', array $parameters = [])
    {
        $this->access = $access;
        $this->reason = $reason;
        $this->parameters = $parameters;
    }

    public static function create(int $access, string $reason = '', array $parameters = []): self
    {
        return new self($access, $reason, $parameters);
    }

    public static function createGranted(string $reason = '', array $parameters = []): self
    {
        return new self(VoterInterface::ACCESS_GRANTED, $reason, $parameters);
    }

    public static function createAbstain(string $reason = '', array $parameters = []): self
    {
        return new self(VoterInterface::ACCESS_ABSTAIN, $reason, $parameters);
    }

    public static function createDenied(string $reason = '', array $parameters = []): self
    {
        return new self(VoterInterface::ACCESS_DENIED, $reason, $parameters);
    }

    public function setReason(string $reason)
    {
        $this->reason = $reason;
    }

    public function getReason(): string
    {
        return $this->reason;
    }

    public function getParameters(): array
    {
        return $this->parameters;
    }
}
