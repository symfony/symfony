<?php

namespace Symfony\Component\HttpKernel;

/**
 * Registry for Requests.
 *
 * Facade for RequestStack that prevents modification of the stack,
 * so that users don't accidentily push()/pop() from the stack and
 * mess up the request cycle.
 */
class RequestContext
{
    private $stack;

    public function __construct(RequestStack $stack)
    {
        $this->stack = $stack;
    }

    /**
     * @return Request
     */
    public function getCurrentRequest()
    {
        return $this->stack->getCurrentRequest();
    }

    /**
     * @return Request
     */
    public function getMasterRequest()
    {
        return $this->stack->getMasterRequest();
    }

    /**
     * @return Request|null
     */
    public function getParentRequest()
    {
        return $this->stack->getParentRequest();
    }
}
