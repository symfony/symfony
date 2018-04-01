<?php

/*
 * This file is part of the Symphony package.
 *
 * (c) Fabien Potencier <fabien@symphony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symphony\Component\Security\Http\Event;

use Symphony\Component\HttpFoundation\Request;
use Symphony\Component\EventDispatcher\Event;
use Symphony\Component\Security\Core\Authentication\Token\TokenInterface;

/**
 * @author Fabien Potencier <fabien@symphony.com>
 */
class InteractiveLoginEvent extends Event
{
    private $request;
    private $authenticationToken;

    public function __construct(Request $request, TokenInterface $authenticationToken)
    {
        $this->request = $request;
        $this->authenticationToken = $authenticationToken;
    }

    /**
     * Gets the request.
     *
     * @return Request A Request instance
     */
    public function getRequest()
    {
        return $this->request;
    }

    /**
     * Gets the authentication token.
     *
     * @return TokenInterface A TokenInterface instance
     */
    public function getAuthenticationToken()
    {
        return $this->authenticationToken;
    }
}
