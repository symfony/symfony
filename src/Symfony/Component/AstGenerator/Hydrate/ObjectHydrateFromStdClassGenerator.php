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

class ObjectHydrateFromStdClassGenerator extends ObjectHydrateGenerator
{
    /**
     * {@inheritdoc}
     */
    protected function createInputExpr(Expr\Variable $inputVariable, $property)
    {
        return new Expr\PropertyFetch($inputVariable, sprintf("{'%s'}", $property));
    }
}
