<?php

namespace Symfony\Bridge\Doctrine\Tests\Fixtures\Messenger;

use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Middleware\MiddlewareInterface;
use Symfony\Component\Messenger\Middleware\StackInterface;

class ThrowingMiddleware implements MiddlewareInterface
{
    public function handle(Envelope $envelope, StackInterface $stack): Envelope
    {
        throw new \RuntimeException('Thrown from middleware.');
    }
}
