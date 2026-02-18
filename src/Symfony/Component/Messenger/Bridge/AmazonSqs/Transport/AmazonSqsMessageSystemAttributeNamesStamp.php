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

use AsyncAws\Sqs\Enum\MessageSystemAttributeName;
use Symfony\Component\Messenger\Stamp\NonSendableStampInterface;

/**
 * @author Ilaria Cangini <ilaria.cangini@kanbanbox.com>
 */
class AmazonSqsMessageSystemAttributeNamesStamp implements NonSendableStampInterface
{
    /**
     * @param array<MessageSystemAttributeName::*, string> $messageSystemAttributeNames
     */
    public function __construct(
        private readonly array $messageSystemAttributeNames,
    ) {
    }

    /**
     * @return array<MessageSystemAttributeName::*, string>
     */
    public function getMessageSystemAttributeNames(): array
    {
        return $this->messageSystemAttributeNames;
    }
}
