<?php

declare(strict_types=1);

namespace Symfony\Component\Messenger\Middleware;


use Symfony\Component\Messenger\Envelope;

class Carrier implements NextHandlerInterface
{
    /**
     * @var MiddlewareInterface
     */
    private $currentMiddleware;

    /**
     * @var NextHandlerInterface
     */
    private $nextHandler;

    /**
     * @param MiddlewareInterface $currentMiddleware
     * @param NextHandlerInterface $nextHandler
     *
     */
    public function __construct(MiddlewareInterface $currentMiddleware, NextHandlerInterface $nextHandler)
    {
        $this->currentMiddleware = $currentMiddleware;
        $this->nextHandler = $nextHandler;
    }


    public function handle(Envelope $envelope) : Envelope
    {
        return $this->currentMiddleware->handle($envelope, $this->nextHandler);
    }
}
