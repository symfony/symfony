<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\DependencyInjection;

use Symfony\Component\DependencyInjection\Exception\LogicException;
use Symfony\Component\ExpressionLanguage\ExpressionFunction;
use Symfony\Component\ExpressionLanguage\ExpressionFunctionProviderInterface;

/**
 * Define some ExpressionLanguage functions.
 *
 * To get a service, use service('request').
 * To get a parameter, use parameter('kernel.debug').
 * To get an env variable, use env('SOME_VARIABLE').
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
class ExpressionLanguageProvider implements ExpressionFunctionProviderInterface
{
    private ?\Closure $serviceCompiler;

    private ?\Closure $getEnv;

    public function __construct(?callable $serviceCompiler = null, ?\Closure $getEnv = null)
    {
        $this->serviceCompiler = null === $serviceCompiler ? null : $serviceCompiler(...);
        $this->getEnv = $getEnv;
    }

    public function getFunctions(): array
    {
        return [
            new ExpressionFunction('service', $this->serviceCompiler ?? fn ($arg) => sprintf('$container->get(%s)', $arg), fn (array $variables, $value) => $variables['container']->get($value)),

            new ExpressionFunction('parameter', fn ($arg) => sprintf('$container->getParameter(%s)', $arg), fn (array $variables, $value) => $variables['container']->getParameter($value)),

            new ExpressionFunction('env', fn ($arg) => sprintf('$container->getEnv(%s)', $arg), function (array $variables, $value) {
                if (!$this->getEnv) {
                    throw new LogicException('You need to pass a getEnv closure to the expression language provider to use the "env" function.');
                }

                return ($this->getEnv)($value);
            }),

            new ExpressionFunction('arg', fn ($arg) => sprintf('$args?->get(%s)', $arg), fn (array $variables, $value) => $variables['args']?->get($value)),
        ];
    }
}
