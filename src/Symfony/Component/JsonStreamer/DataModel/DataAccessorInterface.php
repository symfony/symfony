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

use PhpParser\Node\Expr;

/**
 * Represents a way to access data on PHP.
 *
 * @author Mathias Arlaud <mathias.arlaud@gmail.com>
 *
 * @internal
 */
interface DataAccessorInterface
{
    /**
     * Converts to "nikic/php-parser" PHP expression.
     */
    public function toPhpExpr(): Expr;
}
