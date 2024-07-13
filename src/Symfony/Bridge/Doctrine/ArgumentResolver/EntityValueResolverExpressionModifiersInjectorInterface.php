<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bridge\Doctrine\ArgumentResolver;

use Symfony\Component\ExpressionLanguage\ExpressionFunction;

/**
 * Return a key named array for adding variables to ExpressionLanguage in EntityResolver.
 *
 * @author Roman JOLY <eltharin18@outlook.fr>
 */
interface EntityValueResolverExpressionModifiersInjectorInterface
{
    /**
     * @return array<string, mixed>
     */
    public function getVariables() : array;

    /**
     * @return array<ExpressionFunction>
     */
    public function getFunctions() : array;
}
