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

use Symfony\Component\Security\Core\Exception\InvalidArgumentException;

/**
 * A Vote is returned by a Voter and contains the access (granted, abstain or denied).
 * It can also contain one or multiple messages explaining the vote decision.
 *
 * @author Dany Maillard <danymaillard93b@gmail.com>
 * @author Antoine Lamirault <lamiraultantoine@gmail.com>
 */
final class Vote
{
    /**
     * @var int One of the VoterInterface::ACCESS_* constants
     */
    private int $access;

    /**
     * @var string[]
     */
    private array $messages;

    private array $context;

    /**
     * @param int $access One of the VoterInterface::ACCESS_* constants
     */
    public function __construct(int $access, string|array $messages = [], array $context = [])
    {
        $this->access = $access;
        $this->setMessages($messages);
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

    /**
     * @param string|string[] $messages
     */
    public static function create(int $access, string|array $messages = [], array $context = []): self
    {
        return new self($access, $messages, $context);
    }

    /**
     * @param string|string[] $messages
     */
    public static function createGranted(string|array $messages = [], array $context = []): self
    {
        return new self(VoterInterface::ACCESS_GRANTED, $messages, $context);
    }

    /**
     * @param string|string[] $messages
     */
    public static function createAbstain(string|array $messages = [], array $context = []): self
    {
        return new self(VoterInterface::ACCESS_ABSTAIN, $messages, $context);
    }

    /**
     * @param string|string[] $messages
     */
    public static function createDenied(string|array $messages = [], array $context = []): self
    {
        return new self(VoterInterface::ACCESS_DENIED, $messages, $context);
    }

    /**
     * @param string|string[] $messages
     */
    public function setMessages(string|array $messages)
    {
        $this->messages = (array) $messages;
        foreach ($this->messages as $message) {
            if (!\is_string($message)) {
                throw new InvalidArgumentException(sprintf('Message must be string, "%s" given.', get_debug_type($message)));
            }
        }
    }

    public function addMessage(string $message)
    {
        $this->messages[] = $message;
    }

    /**
     * @return string[]
     */
    public function getMessages(): array
    {
        return $this->messages;
    }

    public function getMessage(): string
    {
        return implode(', ', $this->messages);
    }

    public function getContext(): array
    {
        return $this->context;
    }
}
