<?php

namespace Symfony\Bundle\SecurityBundle\Security\Authentication;

use Symfony\Component\EventDispatcher\EventInterface;
use Symfony\Component\HttpFoundation\Request;

interface AuthenticationFailureHandlerInterface
{
    function onAuthenticationFailure(EventInterface $event, Request $request, \Exception $exception);
}