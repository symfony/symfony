<?php

namespace Symfony\Component\Security\Http\Session;

use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\HttpFoundation\Request;

interface SessionAuthenticationStrategyInterface
{
    function onAuthentication(Request $request, TokenInterface $token);
}