<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Security\Core\Authorization;

use Symfony\Component\ExpressionLanguage\ExpressionFunction;
use Symfony\Component\ExpressionLanguage\ExpressionFunctionProviderInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;

/**
 * Define some ExpressionLanguage functions.
 *
 * The functions read the "token" and "auth_checker" variables that authorization expressions
 * provide. Pass the services to the constructor to make them evaluable where those are missing.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
class ExpressionLanguageProvider implements ExpressionFunctionProviderInterface
{
    private bool $hasServices;

    /**
     * @param RequestStack|null $requestStack Makes the functions throw outside of a request rather than
     *                                        report that nobody is authenticated
     */
    public function __construct(
        private ?AuthorizationCheckerInterface $authorizationChecker = null,
        private ?TokenStorageInterface $tokenStorage = null,
        private ?RequestStack $requestStack = null,
    ) {
        $this->hasServices = $authorizationChecker || $tokenStorage || $requestStack;
    }

    public function getFunctions(): array
    {
        return [
            new ExpressionFunction('is_authenticated', $this->compiler('is_authenticated', static fn () => '$auth_checker->isGranted("IS_AUTHENTICATED")'), fn (array $variables) => $this->authChecker($variables, 'is_authenticated')->isGranted('IS_AUTHENTICATED')),

            new ExpressionFunction('is_fully_authenticated', $this->compiler('is_fully_authenticated', static fn () => '$token && $auth_checker->isGranted("IS_AUTHENTICATED_FULLY")'), fn (array $variables) => $this->token($variables, 'is_fully_authenticated') && $this->authChecker($variables, 'is_fully_authenticated')->isGranted('IS_AUTHENTICATED_FULLY')),

            new ExpressionFunction('is_granted', $this->compiler('is_granted', static fn ($attributes, $object = 'null') => \sprintf('$auth_checker->isGranted(%s, %s)', $attributes, $object)), fn (array $variables, $attributes, $object = null) => $this->authChecker($variables, 'is_granted')->isGranted($attributes, $object)),

            new ExpressionFunction('is_remember_me', $this->compiler('is_remember_me', static fn () => '$token && $auth_checker->isGranted("IS_REMEMBERED")'), fn (array $variables) => $this->token($variables, 'is_remember_me') && $this->authChecker($variables, 'is_remember_me')->isGranted('IS_REMEMBERED')),

            new ExpressionFunction('current_user', $this->compiler('current_user', static fn () => '$token?->getUser()'), fn (array $variables) => $this->token($variables, 'current_user')?->getUser()),
        ];
    }

    /**
     * A compiled expression references the variables, so it cannot fall back to the services.
     */
    private function compiler(string $function, \Closure $compile): \Closure
    {
        if (!$this->hasServices) {
            return $compile;
        }

        return static fn () => throw new \LogicException(\sprintf('The "%s()" function cannot be compiled, it can only be evaluated.', $function));
    }

    private function token(array $variables, string $function): ?TokenInterface
    {
        // an explicit null "token" variable means nobody is authenticated: isGrantedForUser()
        // pushes the token of the user being checked, which must win over the authenticated one
        if (\array_key_exists('token', $variables)) {
            return $variables['token'];
        }

        $this->checkRequest($function);

        return $this->tokenStorage?->getToken();
    }

    private function authChecker(array $variables, string $function): AuthorizationCheckerInterface
    {
        if (\array_key_exists('auth_checker', $variables)) {
            return $variables['auth_checker'];
        }

        $this->checkRequest($function);

        return $this->authorizationChecker ?? throw new \LogicException(\sprintf('The "%s()" function cannot be evaluated without an "auth_checker" variable, unless an authorization checker is passed to "%s".', $function, self::class));
    }

    /**
     * Reporting that nobody is authenticated outside of a request would silently change the
     * meaning of the expression, so it throws instead.
     */
    private function checkRequest(string $function): void
    {
        if ($this->requestStack && !$this->requestStack->getMainRequest()) {
            throw new \LogicException(\sprintf('The "%s()" function cannot be evaluated outside of a request.', $function));
        }
    }
}
