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
 * @author Roman JOLY <eltharin18@outlook.fr>
 */
class Vote implements VoteInterface
{
    /**
     * @var string[]
     */
    private array $messages;

    /**
     * @param int $access One of the VoterInterface constants (ACCESS_GRANTED, ACCESS_ABSTAIN, ACCESS_DENIED)
     *                    or an integer when scoring is false
     */
    public function __construct(
        private int $access,
        string|array $messages = [],
        private array $context = [],
        private $scoring = false,
    ) {
        if (!$scoring && !\in_array($access, [VoterInterface::ACCESS_GRANTED, VoterInterface::ACCESS_ABSTAIN, VoterInterface::ACCESS_DENIED], true)) {
            throw new \LogicException(\sprintf('"$access" must return one of "%s" constants ("ACCESS_GRANTED", "ACCESS_DENIED" or "ACCESS_ABSTAIN") when "$scoring" is false, "%s" returned.', VoterInterface::class, $access));
        }
        $this->setMessages($messages);
    }

    public function __debugInfo(): array
    {
        return [
            'message' => $this->getMessage(),
            'context' => $this->context,
            'voteResultMessage' => $this->getVoteResultMessage(),
        ];
    }

    public function getAccess(): int
    {
        return $this->access;
    }

    public function isGranted(): bool
    {
        return true === $this->access || $this->access > 0;
    }

    public function isAbstain(): bool
    {
        return VoterInterface::ACCESS_ABSTAIN === $this->access;
    }

    public function isDenied(): bool
    {
        return false === $this->access || $this->access < 0;
    }

    /**
     * @param string|string[] $messages
     */
    public function setMessages(string|array $messages): void
    {
        $this->messages = (array) $messages;
        foreach ($this->messages as $message) {
            if (!\is_string($message)) {
                throw new InvalidArgumentException(\sprintf('Message must be string, "%s" given.', get_debug_type($message)));
            }
        }
    }

    public function addMessage(string $message): void
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

    public function getVoteResultMessage(): string
    {
        return $this->scoring ? 'SCORE : '.$this->access : match ($this->access) {
            VoterInterface::ACCESS_GRANTED => 'ACCESS GRANTED',
            VoterInterface::ACCESS_DENIED => 'ACCESS DENIED',
            VoterInterface::ACCESS_ABSTAIN => 'ACCESS ABSTAIN',
            default => 'UNKNOWN ACCESS TYPE',
        };
    }

    public function getContext(): array
    {
        return $this->context;
    }
}
