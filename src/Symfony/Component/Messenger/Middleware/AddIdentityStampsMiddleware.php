<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Messenger\Middleware;

use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Stamp\CausationStamp;
use Symfony\Component\Messenger\Stamp\CorrelationStamp;
use Symfony\Component\Messenger\Stamp\MessageIdStamp;

/**
 * Identifies the dispatched messages and the flow they belong to.
 *
 * Each message gets a MessageIdStamp. A message that opens a flow also gets a CorrelationStamp
 * built from that id, and a message dispatched while another one is handled gets a CausationStamp
 * holding the id of that other message. Stamps already carried by the message are kept as they are.
 *
 * This middleware must run before PropagateStampsMiddleware: the latter copies the correlation of
 * the message being handled onto the messages dispatched while handling it, which requires the id
 * to be on the envelope it pushes.
 *
 * @see PropagateStampsMiddleware
 */
final class AddIdentityStampsMiddleware implements MiddlewareInterface
{
    private \Closure $generateId;

    /**
     * @var list<Envelope>
     */
    private array $stack = [];

    /**
     * @param \Closure(): (string|\Stringable) $generateId
     */
    public function __construct(?\Closure $generateId = null)
    {
        $this->generateId = $generateId ?? static fn (): string => bin2hex(random_bytes(16));
    }

    public function handle(Envelope $envelope, StackInterface $stack): Envelope
    {
        if (null === $messageId = $envelope->last(MessageIdStamp::class)) {
            $envelope = $envelope->with($messageId = new MessageIdStamp(($this->generateId)()));
        }

        if (!$this->stack) {
            if (null === $envelope->last(CorrelationStamp::class)) {
                $envelope = $envelope->with(new CorrelationStamp($messageId->getId()));
            }
        } elseif (null === $envelope->last(CausationStamp::class) && null !== $causedBy = end($this->stack)->last(MessageIdStamp::class)) {
            $envelope = $envelope->with(new CausationStamp($causedBy->getId()));
        }

        $this->stack[] = $envelope;

        try {
            return $stack->next()->handle($envelope, $stack);
        } finally {
            array_pop($this->stack);
        }
    }
}
