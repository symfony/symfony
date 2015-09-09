<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\SecurityBundle\ArgumentResolver;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Controller\ArgumentResolver\ArgumentResolverInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

/**
 * Resolves a typehint for UserInterface in a controller to the current user.
 *
 * @author Wouter J <wouter@wouterj.nl>
 */
class UserArgumentResolver implements ArgumentResolverInterface
{
    /**
     * @var TokenStorageInterface
     */
    private $tokenStorage;

    public function __construct(TokenStorageInterface $tokenStorage)
    {
        $this->tokenStorage = $tokenStorage;
    }

    /**
     * {@inheritdoc}
     */
    public function supports(Request $request, \ReflectionParameter $parameter)
    {
        $class = $parameter->getClass();
        $userInterface = 'Symfony\Component\Security\Core\User\UserInterface';
        $userImplementation = 'Symfony\Component\Security\Core\User\User';

        return null !== $class && ($userInterface === $class->getName() || $userImplementation === $class->getName());
    }

    /**
     * {@inheritdoc}
     */
    public function resolve(Request $request, \ReflectionParameter $parameter)
    {
        if (null === $token = $this->tokenStorage->getToken()) {
            return;
        }

        if (!is_object($user = $token->getUser())) {
            // e.g. anonymous authentication
            return;
        }

        return $user;
    }
}
