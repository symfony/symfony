<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Messenger\Transport\Sender;

use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\TopicsResolver\TopicsResolverInterface;

/**
 * Maps a message to a list of senders.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 *
 * @experimental in 4.2
 */
class SendersLocator implements SendersLocatorInterface
{
    private $topicsResolver;
    private $senders;
    private $sendAndHandle;

    /**
     * @param TopicsResolverInterface $topicsResolver
     * @param SenderInterface[][]     $senders
     * @param bool[]                  $sendAndHandle
     */
    public function __construct(TopicsResolverInterface $topicsResolver, array $senders, array $sendAndHandle = array())
    {
        $this->topicsResolver = $topicsResolver;
        $this->senders = $senders;
        $this->sendAndHandle = $sendAndHandle;
    }

    /**
     * {@inheritdoc}
     */
    public function getSenders(Envelope $envelope, ?bool &$handle = false): iterable
    {
        $handle = false;
        $sender = null;
        $seen = array();

        foreach ($this->topicsResolver->getTopics($envelope) as $topic) {
            foreach ($this->senders[$topic] ?? array() as $sender) {
                if (!\in_array($sender, $seen, true)) {
                    yield $seen[] = $sender;
                }
            }
            $handle = $handle ?: $this->sendAndHandle[$topic] ?? false;
        }

        foreach ($this->senders['*'] ?? array() as $sender) {
            if (!\in_array($sender, $seen, true)) {
                yield $seen[] = $sender;
            }
        }
        $handle = ($handle ?: $this->sendAndHandle['*'] ?? false) || null === $sender;
    }
}
