<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Mailer\Event;

use Symfony\Component\Mailer\Envelope;
use Symfony\Component\Messenger\Stamp\StampInterface;
use Symfony\Component\Mime\RawMessage;

/**
 * Allows the transformation of a Message, the Envelope, and the Messenger stamps before the email is sent to the Messenger bus.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
final class QueuingMessageEvent extends MessageEvent
{
    /** @var StampInterface[] */
    private array $stamps = [];

    public function __construct(RawMessage $message, Envelope $envelope, string $transport)
    {
        parent::__construct($message, $envelope, $transport, true);
    }

    public function addStamp(StampInterface $stamp): void
    {
        $this->stamps[] = $stamp;
    }

    /**
     * @return StampInterface[]
     */
    public function getStamps(): array
    {
        return $this->stamps;
    }
}
