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

/**
 * Stamp applied when a messages needs to be redelivered.
 *
 * @experimental in 4.3
 */
class RedeliveryStamp implements StampInterface
{
    private $retryCount;
    private $senderClassOrAlias;

    /**
     * @param string $senderClassOrAlias Alias from SendersLocator or just the class name
     */
    public function __construct(int $retryCount, string $senderClassOrAlias)
    {
        $this->retryCount = $retryCount;
        $this->senderClassOrAlias = $senderClassOrAlias;
    }

    public function getRetryCount(): int
    {
        return $this->retryCount;
    }

    /**
     * Needed for this class to serialize through Symfony's serializer.
     *
     * @internal
     */
    public function getSenderClassOrAlias(): string
    {
        return $this->senderClassOrAlias;
    }

    public function shouldRedeliverToSender(string $senderClass, ?string $senderAlias): bool
    {
        if (null !== $senderAlias && $senderAlias === $this->senderClassOrAlias) {
            return true;
        }

        if ($senderClass === $this->senderClassOrAlias) {
            return true;
        }

        return false;
    }
}
