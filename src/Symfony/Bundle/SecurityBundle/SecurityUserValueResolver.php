<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\SecurityBundle;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Controller\ArgumentValueResolverInterface;
use Symfony\Component\HttpKernel\ControllerMetadata\ArgumentMetadata;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Http\Controller\UserValueResolver;

@trigger_error(sprintf('The "%s" class is deprecated since Symfony 4.1, use "%s" instead.', SecurityUserValueResolver::class, UserValueResolver::class), \E_USER_DEPRECATED);

/**
 * Supports the argument type of {@see UserInterface}.
 *
 * @author Iltar van der Berg <kjarli@gmail.com>
 *
 * @deprecated since Symfony 4.1, use {@link UserValueResolver} instead
 */
final class SecurityUserValueResolver implements ArgumentValueResolverInterface
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
            return false;
        }

        $user = $token->getUser();

        // in case it's not an object we cannot do anything with it; E.g. "anon."
        return $user instanceof UserInterface;
    }

    public function resolve(Request $request, ArgumentMetadata $argument): iterable
    {
        yield $this->tokenStorage->getToken()->getUser();
    }
}
