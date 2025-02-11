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
 * A Vote is returned by a Voter and contains the access and some messages.
 *
 * @author Roman JOLY <eltharin18@outlook.fr>
 */
class Vote implements VoteInterface
{
    private const VALID_VOTES = [
        VoterInterface::ACCESS_GRANTED => true,
        VoterInterface::ACCESS_DENIED => true,
        VoterInterface::ACCESS_ABSTAIN => true,
    ];

    public function __construct(
        private int $access,
        private string|array $messages = [],
    ) {
        if (!\in_array($access, [VoterInterface::ACCESS_GRANTED, VoterInterface::ACCESS_ABSTAIN, VoterInterface::ACCESS_DENIED], true)) {
            throw new \LogicException(\sprintf('"$access" must return one of "%s" constants ("ACCESS_GRANTED", "ACCESS_DENIED" or "ACCESS_ABSTAIN"), "%s" returned.', VoterInterface::class, $access));
        }
        $this->setMessages($messages);
    }

    public function getAccess(): int
    {
        return $this->access;
    }

    /**
     * @return string[]
     */
    public function getMessages(): array
    {
        return $this->messages;
    }

    /**
     * @throws \InvalidArgumentException
     */
    public function setMessages(string|array $messages): void
    {
        $this->messages = (array) $messages;
        foreach ($this->messages as $message) {
            if (!\is_string($message)) {
                throw new \InvalidArgumentException(\sprintf('Message must be string, "%s" given.', get_debug_type($message)));
            }
        }
    }
}
