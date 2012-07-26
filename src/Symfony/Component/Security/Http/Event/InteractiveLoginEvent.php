<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Security\Http\Event;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\EventDispatcher\Event;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;

class InteractiveLoginEvent extends Event
{
    /**
     * @var Request
     */
    private $request;

    /**
     * @var TokenInterface
     */
    private $authenticationToken;

    /**
     * Constructor.
     *
     * @param Request        $request
     * @param TokenInterface $authenticationToken
     */
    public function __construct(Request $request, TokenInterface $authenticationToken)
    {
        $this->request = $request;
        $this->authenticationToken = $authenticationToken;
    }

    /**
     * Gets the request.
     *
     * @return Request
     */
    public function getRequest()
    {
        return $this->request;
    }

    /**
     * Gets the authentication token.
     *
     * @return TokenInterface
     */
    public function getAuthenticationToken()
    {
        return $this->authenticationToken;
    }
}
