<?php

namespace Symfony\Component\Security\Http\Authentication;

use Symfony\Component\EventDispatcher\EventInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\HttpFoundation\Request;

interface AuthenticationSuccessHandlerInterface
{
    function onAuthenticationSuccess(EventInterface $event, Request $request, TokenInterface $token);
}