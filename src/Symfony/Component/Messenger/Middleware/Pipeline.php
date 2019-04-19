<?php

declare(strict_types=1);

namespace Symfony\Component\Messenger\Middleware;


use function array_reverse;
use Symfony\Component\Messenger\Envelope;

class Pipeline
{
    /**
     * @var NextHandlerInterface
     */
    private $handler;

    public function __construct(array $middlewares)
    {
        $this->handler = new Recipient();
        foreach (array_reverse($middlewares) as $middleware) {
            $this->handler = new Carrier($middleware, $this->handler);
        }
    }

    public function handle(Envelope $envelope) : Envelope
    {
        return $this->handler->handle($envelope);
    }
}
