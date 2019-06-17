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
use Symfony\Component\Messenger\Envelope;

/**
 * Stamp applied when a messages needs to be redelivered.
 *
 * @experimental in 4.3
 */
class RedeliveryStamp implements StampInterface
{
    private $retryCount;
    private $senderClassOrAlias;
    private $redeliveredAt;
    private $exceptionMessage;
    private $flattenException;

    /**
     * @param string $senderClassOrAlias Alias from SendersLocator or just the class name
     */
    public function __construct(int $retryCount, string $senderClassOrAlias, string $exceptionMessage = null, FlattenException $flattenException = null)
    {
        $this->retryCount = $retryCount;
        $this->senderClassOrAlias = $senderClassOrAlias;
        $this->exceptionMessage = $exceptionMessage;
        $this->flattenException = $flattenException;
        $this->redeliveredAt = new \DateTimeImmutable();
    }

    public static function getRetryCountFromEnvelope(Envelope $envelope): int
    {
        /** @var self|null $retryMessageStamp */
        $retryMessageStamp = $envelope->last(self::class);

        return $retryMessageStamp ? $retryMessageStamp->getRetryCount() : 0;
    }

    public function getRetryCount(): int
    {
        return $this->retryCount;
    }

    /**
     * The target sender this should be redelivered to.
     *
     * @internal
     */
    public function getSenderClassOrAlias(): string
    {
        return $this->senderClassOrAlias;
    }

    public function getExceptionMessage(): ?string
    {
        return $this->exceptionMessage;
    }

    public function getFlattenException(): ?FlattenException
    {
        return $this->flattenException;
    }

    public function getRedeliveredAt(): \DateTimeInterface
    {
        return $this->redeliveredAt;
    }
}
