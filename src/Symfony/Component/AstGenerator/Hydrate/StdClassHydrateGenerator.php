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

use PhpParser\Node\Name;
use PhpParser\Node\Expr;

/**
 * Create AST Statement to normalize a Class into a stdClassObject.
 *
 * @author Joel Wurtz <joel.wurtz@gmail.com>
 */
class StdClassHydrateGenerator extends HydrateFromObjectGenerator
{
    /**
     * {@inheritdoc}
     */
    protected function getAssignStatement($dataVariable)
    {
        return new Expr\Assign($dataVariable, new Expr\New_(new Name('\\stdClass')));
    }

    /**
     * {@inheritdoc}
     */
    protected function getSubAssignVariableStatement($dataVariable, $property)
    {
        return new Expr\PropertyFetch($dataVariable, sprintf("{'%s'}", $property));
    }
}
