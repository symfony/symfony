<?php

namespace Symfony\Component\Messenger\Transport\Sender;

use Symfony\Component\Messenger\Envelope;

interface BatchSenderInterface
{
    /**
     * @param Envelope[] $envelopes
     * @return Envelope[]
     */
    public function sendBatch(array $envelopes): array;
}
