<?php

namespace Symfony\Component\Form;

/**
 * An interface that has to be implemented by request
 * handlers that can be chained.
 *
 * @author Wouter J <wouter@wouterj.nl>
 */
interface ChainableRequestHandlerInterface extends RequestHandlerInterface
{
    /**
     * Returns whether this request handler is able to handle the request.
     *
     * @param mixed $request
     *
     * @return bool
     */
    public function supports($request = null);
}
