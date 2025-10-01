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
use Symfony\Component\HttpKernel\Controller\ValueResolverInterface;
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
final class UserValueResolver implements ValueResolverInterface
{
    public function __construct(
        private TokenStorageInterface $tokenStorage,
    ) {
    }

    public function resolve(Request $request, ArgumentMetadata $argument): array
    {
        // with the attribute, the type can be any UserInterface implementation
        // otherwise, the type must be UserInterface
        if (UserInterface::class !== $argument->getType() && !$argument->getAttributesOfType(CurrentUser::class, ArgumentMetadata::IS_INSTANCEOF)) {
            return [];
        }

        if (null === $user = $this->tokenStorage->getToken()?->getUser()) {
            // if no user is present but a default value exists we use it to prevent the EntityValueResolver or others
            // from attempting resolution of the User as the current logged in user was requested here
            if ($argument->hasDefaultValue()) {
                return [$argument->getDefaultValue()];
            }

            if (!$argument->isNullable()) {
                throw new AccessDeniedException(\sprintf('There is no logged-in user to pass to $%s, make the argument nullable if you want to allow anonymous access to the action.', $argument->getName()));
            }

            return [null];
        }

        if (null === $argument->getType() || $user instanceof ($argument->getType())) {
            return [$user];
        }

        $types = explode('|', $argument->getType());
        foreach ($types as $type) {
            if ($user instanceof $type) {
                return [$user];
            }
        }

        throw new AccessDeniedException(\sprintf('The logged-in user is an instance of "%s" but a user of type "%s" is expected.', $user::class, $argument->getType()));
    }
}
