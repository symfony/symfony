<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\JsonEncoder\DataModel;

use PhpParser\BuilderFactory;
use PhpParser\Node\Expr;

/**
 * Defines the way to access a scalar value.
 *
 * @author Mathias Arlaud <mathias.arlaud@gmail.com>
 *
 * @internal
 */
final class ScalarDataAccessor implements DataAccessorInterface
{
    public function __construct(
        private mixed $value,
    ) {
    }

    public function toPhpExpr(): Expr
    {
        return (new BuilderFactory())->val($this->value);
    }
}
