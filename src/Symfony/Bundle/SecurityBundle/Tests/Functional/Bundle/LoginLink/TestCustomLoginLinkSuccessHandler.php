<?php

namespace Symfony\Bundle\SecurityBundle\Tests\Functional\Bundle\LoginLink;

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Http\Authentication\AuthenticationSuccessHandlerInterface;

class TestCustomLoginLinkSuccessHandler implements AuthenticationSuccessHandlerInterface
{
    public function onAuthenticationSuccess(Request $request, TokenInterface $token)
    {
        return new JsonResponse(['message' => sprintf('Welcome %s!', $token->getUsername())]);
    }
}
