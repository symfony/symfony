<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Messenger\Exception;

/**
 * Thrown when a batch dispatch exceeds a transport's maximum batch size.
 *
 * @author Joppe De Cuyper <hello@joppe.dev>
 */
class BatchSizeExceededException extends LogicException
{
    public function __construct(
        private string $transportName,
        private int $batchSize,
        private int $maxBatchSize,
    ) {
        parent::__construct(\sprintf(
            'Transport "%s" supports a maximum batch size of %d, but %d messages were provided.',
            $transportName,
            $maxBatchSize,
            $batchSize,
        ));
    }

    public function getTransportName(): string
    {
        return $this->transportName;
    }

    public function getBatchSize(): int
    {
        return $this->batchSize;
    }

    public function getMaxBatchSize(): int
    {
        return $this->maxBatchSize;
    }
}
