<?php

namespace Symfony\Bundle\SecurityBundle\ArgumentResolver;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Controller\ArgumentResolver\ArgumentResolverInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

/**
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

        return null !== $class && ($userInterface === $class || $class->implementsInterface($userInterface));
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
