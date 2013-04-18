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

use Symfony\Component\HttpFoundation\Request;

/**
 * Request stack that controls the lifecycle of requests.
 *
 * Notifies services of changes in the stack.
 *
 * @author Benjamin Eberlei <kontakt@beberlei.de>
 */
class RequestStack
{
    /**
     * @var Request[]
     */
    private $requests = array();

    public function push(Request $request)
    {
        $this->requests[] = $request;
    }

    /**
     * Pop the current request from the stack.
     *
     * This operation lets the current request go out of scope.
     *
     * @return Request
     */
    public function pop()
    {
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
     * @return Request|null
     */
    public function getMasterRequest()
    {
        if (!$this->requests) {
            return null;
        }

        return $this->requests[0];
    }

    /**
     * Return the parent request of the current.
     *
     * If current Request is the master request, method returns null.
     *
     * @return Request
     */
    public function getParentRequest()
    {
        $pos = count($this->requests) - 2;

        if (!isset($this->requests[$pos])) {
            return null;
        }

        return $this->requests[$pos];
    }
}
