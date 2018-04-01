<?php

/*
 * This file is part of the Symphony package.
 *
 * (c) Fabien Potencier <fabien@symphony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symphony\Component\Security\Http\Controller;

use Symphony\Component\HttpFoundation\Request;
use Symphony\Component\HttpKernel\Controller\ArgumentValueResolverInterface;
use Symphony\Component\HttpKernel\ControllerMetadata\ArgumentMetadata;
use Symphony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symphony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symphony\Component\Security\Core\User\UserInterface;

/**
 * Supports the argument type of {@see UserInterface}.
 *
 * @author Iltar van der Berg <kjarli@gmail.com>
 */
final class UserValueResolver implements ArgumentValueResolverInterface
{
    private $tokenStorage;

    public function __construct(TokenStorageInterface $tokenStorage)
    {
        $this->tokenStorage = $tokenStorage;
    }

    public function supports(Request $request, ArgumentMetadata $argument)
    {
        // only security user implementations are supported
        if (UserInterface::class !== $argument->getType()) {
            return false;
        }

        $token = $this->tokenStorage->getToken();
        if (!$token instanceof TokenInterface) {
            return false;
        }

        $user = $token->getUser();

        // in case it's not an object we cannot do anything with it; E.g. "anon."
        return $user instanceof UserInterface;
    }

    public function resolve(Request $request, ArgumentMetadata $argument)
    {
        yield $this->tokenStorage->getToken()->getUser();
    }
}
