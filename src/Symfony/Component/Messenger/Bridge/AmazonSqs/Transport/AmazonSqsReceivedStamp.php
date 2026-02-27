<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Messenger\Bridge\AmazonSqs\Transport;

use Symfony\Component\Messenger\Stamp\NonSendableStampInterface;

/**
 * @author Jérémy Derussé <jeremy@derusse.com>
 */
class AmazonSqsReceivedStamp implements NonSendableStampInterface
{
    public function __construct(
        private string $id,
        private array $systemAttributes = [],
    ) {
    }

    public function getId(): string
    {
        return $this->id;
    }

    /**
     * @return array<string, string> SQS system attributes (e.g. ApproximateReceiveCount, SentTimestamp)
     */
    public function getSystemAttributes(): array
    {
        return $this->systemAttributes;
    }
}
