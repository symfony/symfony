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

use Symfony\Component\ErrorHandler\Exception\FlattenException;
use Symfony\Component\Messenger\Envelope;

/**
 * Stamp applied when a messages needs to be redelivered.
 */
final class RedeliveryStamp implements StampInterface
{
    private $retryCount;
    private $redeliveredAt;
    private $exceptionMessage;
    private $flattenException;

    public function __construct(int $retryCount, string $exceptionMessage = null, FlattenException $flattenException = null, \DateTimeInterface $redeliveredAt = null)
    {
        $this->retryCount = $retryCount;
        $this->redeliveredAt = $redeliveredAt ?? new \DateTimeImmutable();

        if (null !== $exceptionMessage) {
            trigger_deprecation('symfony/messenger', '5.2', sprintf('Using the "$exceptionMessage" parameter in the "%s" class is deprecated, use the "%s" class instead.', self::class, ErrorDetailsStamp::class));
        }
        $this->exceptionMessage = $exceptionMessage;

        if (null !== $flattenException) {
            trigger_deprecation('symfony/messenger', '5.2', sprintf('Using the "$flattenException" parameter in the "%s" class is deprecated, use the "%s" class instead.', self::class, ErrorDetailsStamp::class));
        }
        $this->flattenException = $flattenException;
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
     * @deprecated since Symfony 5.2, use ErrorDetailsStamp instead.
     */
    public function getExceptionMessage(): ?string
    {
        trigger_deprecation('symfony/messenger', '5.2', sprintf('Using the "getExceptionMessage()" method of the "%s" class is deprecated, use the "%s" class instead.', self::class, ErrorDetailsStamp::class));

        return $this->exceptionMessage;
    }

    /**
     * @deprecated since Symfony 5.2, use ErrorDetailsStamp instead.
     */
    public function getFlattenException(): ?FlattenException
    {
        trigger_deprecation('symfony/messenger', '5.2', sprintf('Using the "getFlattenException()" method of the "%s" class is deprecated, use the "%s" class instead.', self::class, ErrorDetailsStamp::class));

        return $this->flattenException;
    }

    public function getRedeliveredAt(): \DateTimeInterface
    {
        return $this->redeliveredAt;
    }
}
