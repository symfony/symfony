<?php

/*
 * This file is part of the Symphony package.
 *
 * (c) Fabien Potencier <fabien@symphony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symphony\Component\DependencyInjection;

use Symphony\Component\ExpressionLanguage\ExpressionFunction;
use Symphony\Component\ExpressionLanguage\ExpressionFunctionProviderInterface;

/**
 * Define some ExpressionLanguage functions.
 *
 * To get a service, use service('request').
 * To get a parameter, use parameter('kernel.debug').
 *
 * @author Fabien Potencier <fabien@symphony.com>
 */
class ExpressionLanguageProvider implements ExpressionFunctionProviderInterface
{
    private $serviceCompiler;

    public function __construct(callable $serviceCompiler = null)
    {
        $this->serviceCompiler = $serviceCompiler;
    }

    public function getFunctions()
    {
        return array(
            new ExpressionFunction('service', $this->serviceCompiler ?: function ($arg) {
                return sprintf('$this->get(%s)', $arg);
            }, function (array $variables, $value) {
                return $variables['container']->get($value);
            }),

            new ExpressionFunction('parameter', function ($arg) {
                return sprintf('$this->getParameter(%s)', $arg);
            }, function (array $variables, $value) {
                return $variables['container']->getParameter($value);
            }),
        );
    }
}
