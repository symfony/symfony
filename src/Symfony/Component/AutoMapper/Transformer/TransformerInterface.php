<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\AutoMapper\Transformer;

use PhpParser\Node\Expr;
use PhpParser\Node\Stmt;
use Symfony\Component\AutoMapper\Extractor\PropertyMapping;
use Symfony\Component\AutoMapper\Generator\UniqueVariableScope;

/**
 * Transformer tell how to transform a property mapping.
 *
 * @internal
 *
 * @author Joel Wurtz <jwurtz@jolicode.com>
 */
interface TransformerInterface
{
    /**
     * Get AST output and expressions for transforming a property mapping given an input.
     *
     * @return [Expr, Stmt[]] First value is the output expression, second value is an array of stmt needed to get the output
     */
    public function transform(Expr $input, PropertyMapping $propertyMapping, UniqueVariableScope $uniqueVariableScope): array;

    /**
     * Get dependencies for this transformer.
     *
     * @return MapperDependency[]
     */
    public function getDependencies(): array;

    /**
     * Should the resulting output be assigned by ref.
     *
     * @return bool
     */
    public function assignByRef(): bool;
}
