<?php

namespace Symfony\Bridge\Doctrine\Tests\Fixtures;

use Symfony\Bridge\Doctrine\Messenger\MessageRecordingEntityInterface;
use Symfony\Bridge\Doctrine\Messenger\MessageRecordingEntityTrait;

final class MessageRecordingEntity implements MessageRecordingEntityInterface
{
    use MessageRecordingEntityTrait;

    public function doRecordMessage(object $message): void
    {
        $this->recordMessage($message);
    }
}
