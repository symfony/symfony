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

    /**
     * @param \DateTimeInterface|null $redeliveredAt
     */
    public function __construct(int $retryCount, $redeliveredAt = null)
    {
        if (2 < \func_num_args() || null !== $redeliveredAt && !$redeliveredAt instanceof \DateTimeInterface) {
            trigger_deprecation('symfony/messenger', '5.2', sprintf('Using parameters "$exceptionMessage" or "$flattenException" of class "%s" is deprecated, use "%s" instead and/or pass "$redeliveredAt" as parameter #2.', self::class, ErrorDetailsStamp::class));
            $this->exceptionMessage = $redeliveredAt instanceof \DateTimeInterface ? null : $redeliveredAt;
            $redeliveredAt = 4 <= \func_num_args() ? func_get_arg(3) : ($redeliveredAt instanceof \DateTimeInterface ? $redeliveredAt : null);
            $this->flattenException = 3 <= \func_num_args() ? func_get_arg(2) : null;
        }

        $this->retryCount = $retryCount;
        $this->redeliveredAt = $redeliveredAt ?? new \DateTimeImmutable();
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
