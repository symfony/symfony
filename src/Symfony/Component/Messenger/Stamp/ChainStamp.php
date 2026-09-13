<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Messenger\Stamp;

use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Exception\InvalidArgumentException;
use Symfony\Component\Messenger\Middleware\ChainMiddleware;

/**
 * Lists the messages to dispatch one after another once the current message is handled.
 *
 * @see ChainMiddleware
 */
final class ChainStamp implements StampInterface
{
    /**
     * @var list<object|Envelope>
     */
    private array $messages = [];

    /**
     * @param object|Envelope ...$messages The messages, or messages pre-wrapped in envelopes, in handling order
     */
    public function __construct(object ...$messages)
    {
        if (!$messages) {
            throw new InvalidArgumentException('A chain needs at least one message.');
        }

        foreach ($messages as $message) {
            // the chain travels inside another envelope, which does not strip these stamps for it
            $this->messages[] = $message instanceof Envelope ? $message->withoutStampsOfType(NonSendableStampInterface::class) : $message;
        }
    }

    /**
     * @return list<object|Envelope>
     */
    public function getMessages(): array
    {
        return $this->messages;
    }
}
