<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\AstGenerator\Hydrate;

use PhpParser\Node\Expr;
use PhpParser\Node\Scalar;

/**
 * Create AST Statement to normalize a Class into a stdClassObject.
 *
 * @author Joel Wurtz <joel.wurtz@gmail.com>
 */
class ArrayHydrateGenerator extends HydrateFromObjectGenerator
{
    /**
     * {@inheritdoc}
     */
    protected function getAssignStatement($dataVariable)
    {
        return new Expr\Assign($dataVariable, new Expr\Array_());
    }

    /**
     * {@inheritdoc}
     */
    protected function getSubAssignVariableStatement($dataVariable, $property)
    {
        return new Expr\ArrayDimFetch($dataVariable, new Scalar\String_($property));
    }
}
