<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HttpKernel;

/**
 * Registry for Requests.
 *
 * Facade for RequestStack that prevents modification of the stack,
 * so that users don't accidentally push()/pop() from the stack and
 * mess up the request cycle.
 *
 * @author Benjamin Eberlei <kontakt@beberlei.de>
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
