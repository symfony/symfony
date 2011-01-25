<?php

namespace Symfony\Component\HttpKernel\Security\Authentication;

use Symfony\Component\EventDispatcher\EventInterface;
use Symfony\Component\Security\Authentication\Token\TokenInterface;
use Symfony\Component\HttpFoundation\Request;

interface AuthenticationSuccessHandlerInterface
{
    function onAuthenticationSuccess(EventInterface $event, Request $request, TokenInterface $token);
}