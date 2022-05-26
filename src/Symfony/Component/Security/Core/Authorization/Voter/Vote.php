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
 * It can also contain a message explaining the vote decision.
 *
 * @author Dany Maillard <danymaillard93b@gmail.com>
 */
final class Vote
{
    /** @var int One of the VoterInterface::ACCESS_* constants */
    private $access;
    private $message;
    private $context;

    /**
     * @param int $access One of the VoterInterface::ACCESS_* constants
     */
    public function __construct(int $access, string $message = '', array $context = [])
    {
        $this->access = $access;
        $this->message = $message;
        $this->context = $context;
    }

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

    public static function create(int $access, string $message = '', array $context = []): self
    {
        return new self($access, $message, $context);
    }

    public static function createGranted(string $message = '', array $context = []): self
    {
        return new self(VoterInterface::ACCESS_GRANTED, $message, $context);
    }

    public static function createAbstain(string $message = '', array $context = []): self
    {
        return new self(VoterInterface::ACCESS_ABSTAIN, $message, $context);
    }

    public static function createDenied(string $message = '', array $context = []): self
    {
        return new self(VoterInterface::ACCESS_DENIED, $message, $context);
    }

    public function setMessage(string $message)
    {
        $this->message = $message;
    }

    public function getMessage(): string
    {
        return $this->message;
    }

    public function getContext(): array
    {
        return $this->context;
    }
}
