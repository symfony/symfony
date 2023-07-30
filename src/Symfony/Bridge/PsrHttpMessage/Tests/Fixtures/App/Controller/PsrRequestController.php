<?php

namespace Symfony\Bridge\PsrHttpMessage\Tests\Fixtures\App\Controller;

use Psr\Http\Message\MessageInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\StreamFactoryInterface;

final class PsrRequestController
{
    private $responseFactory;
    private $streamFactory;

    public function __construct(ResponseFactoryInterface $responseFactory, StreamFactoryInterface $streamFactory)
    {
        $this->responseFactory = $responseFactory;
        $this->streamFactory = $streamFactory;
    }

    public function serverRequestAction(ServerRequestInterface $request): ResponseInterface
    {
        return $this->responseFactory
            ->createResponse()
            ->withBody($this->streamFactory->createStream(sprintf('<html><body>%s</body></html>', $request->getMethod())));
    }

    public function requestAction(RequestInterface $request): ResponseInterface
    {
        return $this->responseFactory
            ->createResponse()
            ->withStatus(403)
            ->withBody($this->streamFactory->createStream(sprintf('<html><body>%s %s</body></html>', $request->getMethod(), $request->getBody()->getContents())));
    }

    public function messageAction(MessageInterface $request): ResponseInterface
    {
        return $this->responseFactory
            ->createResponse()
            ->withStatus(422)
            ->withBody($this->streamFactory->createStream(sprintf('<html><body>%s</body></html>', $request->getHeader('X-My-Header')[0])));
    }
}
