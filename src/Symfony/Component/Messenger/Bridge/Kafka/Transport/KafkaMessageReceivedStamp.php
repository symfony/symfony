<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Messenger\Bridge\Kafka\Transport;

use RdKafka\Message;
use Symfony\Component\Messenger\Stamp\NonSendableStampInterface;

/**
 * @author Konstantin Scheumann <konstantin@konstantin.codes>
 */
final class KafkaMessageReceivedStamp implements NonSendableStampInterface
{
    public function __construct(
        private Message $message
    ) {
    }

    public function getMessage(): Message
    {
        return $this->message;
    }
}
