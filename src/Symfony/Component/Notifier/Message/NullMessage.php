<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Notifier\Message;

/**
 * @author Jan Schädlich <jan.schaedlich@sensiolabs.de>
 */
final class NullMessage implements MessageInterface
{
    private MessageInterface $decoratedMessage;

    public function __construct(MessageInterface $message)
    {
        $this->decoratedMessage = $message;
    }

    public function getRecipientId(): ?string
    {
        return $this->decoratedMessage->getRecipientId();
    }

    public function getSubject(): string
    {
        return $this->decoratedMessage->getSubject();
    }

    public function getOptions(): ?MessageOptionsInterface
    {
        return $this->decoratedMessage->getOptions();
    }

    public function getTransport(): ?string
    {
        return $this->decoratedMessage->getTransport() ?? null;
    }
}
