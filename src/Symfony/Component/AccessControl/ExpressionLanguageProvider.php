<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\AccessControl;

use Symfony\Component\ExpressionLanguage\ExpressionFunction;
use Symfony\Component\ExpressionLanguage\ExpressionFunctionProviderInterface;


/**
 * @experimental
 */
class ExpressionLanguageProvider implements ExpressionFunctionProviderInterface
{
    public function getFunctions(): array
    {
        return [
            new ExpressionFunction('is_authenticated', static fn () => '$token && $trust_resolver->isAuthenticated($token)', static fn (array $variables) => $variables['token'] && $variables['trust_resolver']->isAuthenticated($variables['token'])),
            new ExpressionFunction('is_fully_authenticated', static fn () => '$token && $trust_resolver->isFullFledged($token)', static fn (array $variables) => $variables['token'] && $variables['trust_resolver']->isFullFledged($variables['token'])),
            new ExpressionFunction('is_remember_me', static fn () => '$token && $trust_resolver->isRememberMe($token)', static fn (array $variables) => $variables['token'] && $variables['trust_resolver']->isRememberMe($variables['token'])),
        ];
    }
}
