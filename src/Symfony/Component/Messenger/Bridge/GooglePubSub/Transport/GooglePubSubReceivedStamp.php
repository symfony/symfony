<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Messenger\Bridge\GooglePubSub\Transport;

use Symfony\Component\Messenger\Stamp\NonSendableStampInterface;

/**
 * @author Richard Henkenjohann <richardhenkenjohann@googlemail.com>
 */
class GooglePubSubReceivedStamp implements NonSendableStampInterface
{
    private $id;
    private $ackId;

    public function __construct(string $id, string $ackId)
    {
        $this->id = $id;
        $this->ackId = $ackId;
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getAckId(): string
    {
        return $this->ackId;
    }
}
