<?php

/*
 * This file is part of the Symphony package.
 *
 * (c) Fabien Potencier <fabien@symphony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symphony\Bundle\SecurityBundle\Tests\Functional\Bundle\JsonLoginBundle\Security\Http;

use Symphony\Component\HttpFoundation\JsonResponse;
use Symphony\Component\HttpFoundation\Request;
use Symphony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symphony\Component\Security\Http\Authentication\AuthenticationSuccessHandlerInterface;

class JsonAuthenticationSuccessHandler implements AuthenticationSuccessHandlerInterface
{
    public function onAuthenticationSuccess(Request $request, TokenInterface $token)
    {
        return new JsonResponse(array('message' => sprintf('Good game @%s!', $token->getUsername())));
    }
}
