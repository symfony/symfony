<?php

namespace Symfony\Component\Form;

use Symfony\Component\Form\Exception\LogicException;

/**
 * A request handler that chains over multiple request handlers,
 * until it finds one that can handle the request.
 *
 * @author Wouter J <wouter@wouterj.nl>
 */
class ChainRequestHandler implements RequestHandlerInterface
{
    /**
     * @var ChainableRequestHandlerInterface[]
     */
    private $requestHandlers;

    public function __construct(array $requestHandlers = array())
    {
        $this->requestHandlers = $requestHandlers;
    }

    /**
     * Adds a new request handler to the chain.
     *
     * @param ChainableRequestHandlerInterface $requestHandler
     */
    public function add(ChainableRequestHandlerInterface $requestHandler)
    {
        $this->requestHandlers[] = $requestHandler;
    }

    /**
     * {@inheritdoc}
     *
     * @throws LogicException When the request could not be handled by the registered request handlers
     */
    public function handleRequest(FormInterface $form, $request = null)
    {
        foreach ($this->requestHandlers as $requestHandler) {
            if ($requestHandler->supports($request)) {
                $requestHandler->handleRequest($form, $request);

                return;
            }
        }

        throw new LogicException('None of the registered request handlers were able to handle the request.');
    }
}
