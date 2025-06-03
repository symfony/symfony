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

final class AmazonSqsFifoStamp implements NonSendableStampInterface
{
    public function __construct(
        private ?string $messageGroupId = null,
        private ?string $messageDeduplicationId = null,
    ) {
    }

    public function getMessageGroupId(): ?string
    {
        return $this->messageGroupId;
    }

    public function getMessageDeduplicationId(): ?string
    {
        return $this->messageDeduplicationId;
    }
}
