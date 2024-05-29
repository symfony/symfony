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
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Controller\ValueResolverInterface;
use Symfony\Component\HttpKernel\ControllerMetadata\ArgumentMetadata;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;

/**
 * @author Konstantin Myakshin <molodchick@gmail.com>
 */
final class SecurityTokenValueResolver implements ValueResolverInterface
{
    public function __construct(private readonly TokenStorageInterface $tokenStorage)
    {
    }

    /**
     * @return TokenInterface[]
     */
    public function resolve(Request $request, ArgumentMetadata $argument): array
    {
        if (!($type = $argument->getType()) || (TokenInterface::class !== $type && !is_subclass_of($type, TokenInterface::class))) {
            return [];
        }

        if (null !== $token = $this->tokenStorage->getToken()) {
            return [$token];
        }

        if ($argument->isNullable()) {
            return [];
        }

        throw new HttpException(Response::HTTP_UNAUTHORIZED, 'A security token is required but the token storage is empty.');
    }
}
