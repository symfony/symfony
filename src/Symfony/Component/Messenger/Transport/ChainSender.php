<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Messenger\Transport;

use Symfony\Component\Messenger\Envelope;

/**
 * @author Tobias Schultze <http://tobion.de>
 */
class ChainSender implements SenderInterface
{
    private $senders;

    /**
     * @param SenderInterface[] $senders
     */
    public function __construct(iterable $senders)
    {
        $this->senders = $senders;
    }

    /**
     * {@inheritdoc}
     */
    public function send(Envelope $message): void
    {
        foreach ($this->senders as $sender) {
            $sender->send($message);
        }
    }
}
