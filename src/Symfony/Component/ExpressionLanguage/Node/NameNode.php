<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\ExpressionLanguage\Node;

use Symfony\Component\ExpressionLanguage\Compiler;

/**
 * @author Fabien Potencier <fabien@symfony.com>
 *
 * @internal
 */
class NameNode extends Node
{
    public function __construct(string $name)
    {
        parent::__construct(
            [],
            ['name' => $name]
        );
    }

    public function compile(Compiler $compiler): void
    {
        $compiler->raw('$'.$this->attributes['name']);
    }

    public function evaluate(array $functions, array $values): mixed
    {
        return $values[$this->attributes['name']];
    }

    public function toArray(): array
    {
        return [$this->attributes['name']];
    }
}
