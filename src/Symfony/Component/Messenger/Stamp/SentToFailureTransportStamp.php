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

use Symfony\Component\Debug\Exception\FlattenException;

/**
 * Stamp applied when a message is sent to the failure transport.
 *
 * @author Ryan Weaver <ryan@symfonycasts.com>
 *
 * @experimental in 4.3
 */
class SentToFailureTransportStamp implements StampInterface
{
    private $exceptionMessage;
    private $originalReceiverName;
    private $flattenException;
    private $sentAt;

    public function __construct(string $exceptionMessage, string $originalReceiverName, FlattenException $flattenException = null)
    {
        $this->exceptionMessage = $exceptionMessage;
        $this->originalReceiverName = $originalReceiverName;
        $this->flattenException = $flattenException;
        $this->sentAt = new \DateTimeImmutable();
    }

    public function getExceptionMessage(): string
    {
        return $this->exceptionMessage;
    }

    public function getOriginalReceiverName(): string
    {
        return $this->originalReceiverName;
    }

    public function getFlattenException(): ?FlattenException
    {
        return $this->flattenException;
    }

    public function getSentAt(): \DateTimeInterface
    {
        return $this->sentAt;
    }
}
