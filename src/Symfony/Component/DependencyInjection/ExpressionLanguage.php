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

use Psr\Cache\CacheItemPoolInterface;
use Symfony\Component\ExpressionLanguage\ConstantFunctionProvider;
use Symfony\Component\ExpressionLanguage\ExpressionLanguage as BaseExpressionLanguage;

if (!class_exists(BaseExpressionLanguage::class)) {
    return;
}

/**
 * Adds some function to the default ExpressionLanguage.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 *
 * @see ExpressionLanguageProvider
 */
class ExpressionLanguage extends BaseExpressionLanguage
{
    public function __construct(?CacheItemPoolInterface $cache = null, iterable $providers = [], ?callable $serviceCompiler = null, ?\Closure $getEnv = null)
    {
        if (!\is_array($providers)) {
            $providers = iterator_to_array($providers, false);
        }

        // prepend the default providers to let users override them easily
        array_unshift($providers, new ExpressionLanguageProvider($serviceCompiler, $getEnv));

        if (class_exists(ConstantFunctionProvider::class)) {
            array_unshift($providers, new ConstantFunctionProvider(['*', '*::*']));
        }

        parent::__construct($cache, $providers);
    }
}
