<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\JsonStreamer\DataModel;

use PhpParser\BuilderFactory;
use PhpParser\Node\Expr;

/**
 * Defines the way to access data using a variable.
 *
 * @author Mathias Arlaud <mathias.arlaud@gmail.com>
 *
 * @internal
 */
final class VariableDataAccessor implements DataAccessorInterface
{
    public function __construct(
        private string $name,
    ) {
    }

    public function toPhpExpr(): Expr
    {
        return (new BuilderFactory())->var($this->name);
    }
}
