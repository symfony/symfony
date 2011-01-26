<?php

namespace Symfony\Bundle\SecurityBundle\Security\Session;

use Symfony\Component\Security\Authentication\Token\TokenInterface;
use Symfony\Component\HttpFoundation\Request;

interface SessionAuthenticationStrategyInterface
{
    function onAuthentication(Request $request, TokenInterface $token);
}