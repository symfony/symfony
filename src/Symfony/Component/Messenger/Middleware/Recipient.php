<?php

declare(strict_types=1);

namespace Symfony\Component\Messenger\Middleware;


use Symfony\Component\Messenger\Envelope;

class Recipient implements NextHandlerInterface
{

    public function handle(Envelope $envelope) : Envelope
    {
        return $envelope;
    }
}
