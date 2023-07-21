<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Messenger\Bridge\Kafka\Stamp;

use Symfony\Component\Messenger\Stamp\NonSendableStampInterface;

final class KafkaMessageStamp implements NonSendableStampInterface
{
    public function __construct(
        public int $partition,
        public int $messageFlags,
        public ?string $key,
    ) {
    }
}
