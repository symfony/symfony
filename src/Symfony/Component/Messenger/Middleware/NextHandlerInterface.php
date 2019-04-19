<?php

declare(strict_types=1);

namespace Symfony\Component\Messenger\Middleware;


use Symfony\Component\Messenger\Envelope;

interface NextHandlerInterface
{
    public function handle(Envelope $envelope) : Envelope;
}
