<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HttpFoundation;

/**
 * Request stack that controls the lifecycle of requests.
 *
 * @author Benjamin Eberlei <kontakt@beberlei.de>
 */
class RequestStack
{
    /**
     * @var Request[]
     */
    private $requests = array();

    /**
     * Pushes a Request on the stack.
     *
     * This method should generally not be called directly as the stack
     * management should be taken care of by the application itself.
     */
    public function push(Request $request)
    {
        $this->requests[] = $request;
    }

    /**
     * Pops the current request from the stack.
     *
     * This operation lets the current request go out of scope.
     *
     * This method should generally not be called directly as the stack
     * management should be taken care of by the application itself.
     *
     * @return Request|null
     */
    public function pop()
    {
        if (!$this->requests) {
            return;
        }

        return array_pop($this->requests);
    }

    /**
     * @return Request|null
     */
    public function getCurrentRequest()
    {
        return end($this->requests) ?: null;
    }

    /**
     * Gets the master Request.
     *
     * Be warned that making your code aware of the master request
     * might make it un-compatible with other features of your framework
     * like ESI support.
     *
     * @return Request|null
     */
    public function getMasterRequest()
    {
        if (!$this->requests) {
            return;
        }

        return $this->requests[0];
    }

    /**
     * Returns the parent request of the current.
     *
     * Be warned that making your code aware of the parent request
     * might make it un-compatible with other features of your framework
     * like ESI support.
     *
     * If current Request is the master request, it returns null.
     *
     * @return Request|null
     */
    public function getParentRequest()
    {
        $pos = count($this->requests) - 2;

        if (!isset($this->requests[$pos])) {
            return;
        }

        return $this->requests[$pos];
    }

    /**
     * Executes callback if there is a current request available
     *
     * @param callable $callback
     * @return mixed
     */
    public function withCurrentRequest($callback)
    {
        return $this->invokeCallbackIfRequestPresent($callback, $this->getCurrentRequest());
    }

    /**
     * Executes callback if there is a master request available
     *
     * @param callable $callback
     * @return mixed
     */
    public function withMasterRequest($callback)
    {
        return $this->invokeCallbackIfRequestPresent($callback, $this->getMasterRequest());
    }

    /**
     * Executes callback if there is a parent request available
     *
     * @param callable $callback
     * @return mixed
     */
    public function withParentRequest($callback)
    {
        return $this->invokeCallbackIfRequestPresent($callback, $this->getParentRequest());
    }

    private static function invokeCallbackIfRequestPresent($callback, Request $request = null)
    {
        if (!is_callable($callback)) {
            throw new \InvalidArgumentException('Invalid callback given');
        }

        if ($request) {
            return $callback($request);
        }
    }
}
