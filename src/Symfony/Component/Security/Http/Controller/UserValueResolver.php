<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Security\Http\Controller;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Controller\ArgumentValueResolverInterface;
use Symfony\Component\HttpKernel\ControllerMetadata\ArgumentMetadata;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\Component\Security\Core\User\UserInterface;

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

    public function supports(Request $request, ArgumentMetadata $argument): bool
    {
        // only security user implementations are supported
        if (UserInterface::class !== $argument->getType()) {
            return false;
        }

        $token = $this->tokenStorage->getToken();
        if (!$token instanceof TokenInterface) {
            $this->maybeThrowAccessDeniedException($argument);

            return false;
        }

        $user = $token->getUser();

        // in case it's not an object we cannot do anything with it; E.g. "anon."
        if (!$user instanceof UserInterface) {
            $this->maybeThrowAccessDeniedException($argument);

            return false;
        }

        return true;
    }

    public function resolve(Request $request, ArgumentMetadata $argument): iterable
    {
        yield $this->tokenStorage->getToken()->getUser();
    }

    private function maybeThrowAccessDeniedException(ArgumentMetadata $argument): void
    {
        if ($argument->hasDefaultValue() || (null !== $argument->getType() && $argument->isNullable())) {
            return;
        }

        // Although not really the responsibility of an ArgumentValueResolverInterface, we need to stop here
        // because otherwise another resolver (like ServiceValueResolver) can try to load the User class
        // from the service container and fail with an exception that is counter-intuitive:
        //
        // Example: Cannot autowire argument $user of "App\Controller::myAction()": it references class "App\User" but no such service exists.
        //
        // By throwing an AccessDeniedException we can redirect the user to a login page.
        throw new AccessDeniedException('No token found in the security context.');
    }
}
