<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\FrameworkBundle\Validator;

use Symfony\Component\ExpressionLanguage\ExpressionFunction;
use Symfony\Component\ExpressionLanguage\ExpressionFunctionProviderInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;

/**
 * Registers security expression functions for use in validator expressions.
 */
class ValidatorSecurityExpressionLanguageProvider implements ExpressionFunctionProviderInterface
{
    public function __construct(
        private readonly AuthorizationCheckerInterface $authorizationChecker,
        private readonly TokenStorageInterface $tokenStorage,
        private readonly RequestStack $requestStack,
    ) {
    }

    public function getFunctions(): array
    {
        $authorizationChecker = $this->authorizationChecker;
        $tokenStorage = $this->tokenStorage;

        return [
            new ExpressionFunction('is_authenticated', self::compilerThrows('is_authenticated'), $this->evaluator('is_authenticated', static fn () => $authorizationChecker->isGranted('IS_AUTHENTICATED'))),

            new ExpressionFunction('is_fully_authenticated', self::compilerThrows('is_fully_authenticated'), $this->evaluator('is_fully_authenticated', static fn () => $tokenStorage->getToken() && $authorizationChecker->isGranted('IS_AUTHENTICATED_FULLY'))),

            new ExpressionFunction('is_granted', self::compilerThrows('is_granted'), $this->evaluator('is_granted', static fn ($attributes, $object = null) => $authorizationChecker->isGranted($attributes, $object))),

            new ExpressionFunction('is_remember_me', self::compilerThrows('is_remember_me'), $this->evaluator('is_remember_me', static fn () => $tokenStorage->getToken() && $authorizationChecker->isGranted('IS_REMEMBERED'))),
        ];
    }

    /**
     * The functions read the security context of the current request. Outside a request, as in a
     * console command or a Messenger worker, there is no context to read: returning false there
     * would silently skip the constraints guarded by the expression, so evaluation throws instead.
     */
    private function evaluator(string $name, \Closure $evaluate): \Closure
    {
        $requestStack = $this->requestStack;

        return static function (array $variables, ...$args) use ($name, $requestStack, $evaluate) {
            if (!$requestStack->getMainRequest()) {
                throw new \LogicException(\sprintf('The "%s()" function cannot be evaluated outside of a request.', $name));
            }

            return $evaluate(...$args);
        };
    }

    /**
     * The functions call the authorization checker bound at registration time, so there is
     * nothing a compiled expression could reference: evaluation is the only supported mode.
     */
    private static function compilerThrows(string $name): \Closure
    {
        return static fn () => throw new \LogicException(\sprintf('The "%s()" function cannot be compiled, it can only be evaluated.', $name));
    }
}
