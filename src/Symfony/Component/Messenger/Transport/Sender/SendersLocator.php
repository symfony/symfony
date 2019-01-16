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
use Symfony\Component\Messenger\Handler\HandlersLocator;

/**
 * Maps a message to a list of senders.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 *
 * @experimental in 4.2
 */
class SendersLocator implements SendersLocatorInterface
{
    private $senders;
    private $sendAndHandle;

    /**
     * @param SenderInterface[][] $senders
     * @param bool[]              $sendAndHandle
     */
    public function __construct(array $senders, array $sendAndHandle = [])
    {
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
        $seen = [];

        foreach (HandlersLocator::listTypes($envelope) as $type) {
            foreach ($this->senders[$type] ?? [] as $alias => $sender) {
                if (!\in_array($sender, $seen, true)) {
                    yield $alias => $seen[] = $sender;
                }
            }
            $handle = $handle ?: $this->sendAndHandle[$type] ?? false;
        }

        $handle = $handle || null === $sender;
    }
}
