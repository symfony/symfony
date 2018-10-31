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

use Symfony\Component\Messenger\Exception\RuntimeException;
use Symfony\Component\Messenger\Handler\HandlersLocator;
use Symfony\Component\Messenger\Transport\Sender\SenderInterface;

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
    public function __construct(array $senders, array $sendAndHandle = array())
    {
        $this->senders = $senders;
        $this->sendAndHandle = $sendAndHandle;
    }

    /**
     * {@inheritdoc}
     */
    public function getSenders(string $name, ?bool &$handle = false): iterable
    {
        $handle = false;
        $sender = null;
        $seen = array();

        foreach (HandlersLocator::listTypes($name) as $type) {
            foreach ($this->senders[$type] ?? array() as $sender) {
                if (!\in_array($sender, $seen, true)) {
                    yield $seen[] = $sender;
                }
            }
            $handle = $handle ?: $this->sendAndHandle[$type] ?? false;
        }

        $handle = $handle || null === $sender;
    }
}
