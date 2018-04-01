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
use Symphony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symphony\Component\Security\Core\User\UserInterface;
use Symphony\Component\EventDispatcher\Event;

/**
 * SwitchUserEvent.
 *
 * @author Fabien Potencier <fabien@symphony.com>
 */
class SwitchUserEvent extends Event
{
    private $request;
    private $targetUser;
    private $token;

    public function __construct(Request $request, UserInterface $targetUser, TokenInterface $token = null)
    {
        $this->request = $request;
        $this->targetUser = $targetUser;
        $this->token = $token;
    }

    /**
     * @return Request
     */
    public function getRequest()
    {
        return $this->request;
    }

    /**
     * @return UserInterface
     */
    public function getTargetUser()
    {
        return $this->targetUser;
    }

    /**
     * @return TokenInterface|null
     */
    public function getToken()
    {
        return $this->token;
    }

    public function setToken(TokenInterface $token)
    {
        $this->token = $token;
    }
}
