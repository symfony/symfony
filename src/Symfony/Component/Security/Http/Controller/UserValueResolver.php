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
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

/**
 * Supports the argument type of {@see UserInterface}.
 *
 * @author Iltar van der Berg <kjarli@gmail.com>
 */
final class UserValueResolver implements ArgumentValueResolverInterface
{
    private TokenStorageInterface $tokenStorage;

    public function __construct(TokenStorageInterface $tokenStorage)
    {
        $this->tokenStorage = $tokenStorage;
    }

    public function supports(Request $request, ArgumentMetadata $argument): bool
    {
        // with the attribute, the type can be any UserInterface implementation
        // otherwise, the type must be UserInterface
        if (UserInterface::class !== $argument->getType() && !$argument->getAttributesOfType(CurrentUser::class, ArgumentMetadata::IS_INSTANCEOF)) {
            return false;
        }

        // if no user is present but a default value exists we delegate to DefaultValueResolver
        if ($argument->hasDefaultValue() && null === $this->tokenStorage->getToken()?->getUser()) {
            return false;
        }

        return true;
    }

    public function resolve(Request $request, ArgumentMetadata $argument): iterable
    {
        $user = $this->tokenStorage->getToken()?->getUser();

        if (null === $user) {
            if (!$argument->isNullable()) {
                throw new AccessDeniedException(sprintf('There is no logged-in user to pass to $%s, make the argument nullable if you want to allow anonymous access to the action.', $argument->getName()));
            }
            yield null;
        } elseif (null === $argument->getType() || $user instanceof ($argument->getType())) {
            yield $user;
        } else {
            throw new AccessDeniedException(sprintf('The logged-in user is an instance of "%s" but a user of type "%s" is expected.', $user::class, $argument->getType()));
        }
    }
}
