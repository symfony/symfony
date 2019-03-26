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
 * Transformer array decorator.
 *
 * @expiremental in 4.3
 *
 * @author Joel Wurtz <jwurtz@jolicode.com>
 */
final class ArrayTransformer implements TransformerInterface
{
    private $itemTransformer;

    public function __construct(TransformerInterface $itemTransformer)
    {
        $this->itemTransformer = $itemTransformer;
    }

    /**
     * {@inheritdoc}
     */
    public function transform(Expr $input, PropertyMapping $propertyMapping, UniqueVariableScope $uniqueVariableScope): array
    {
        $valuesVar = new Expr\Variable($uniqueVariableScope->getUniqueName('values'));
        $statements = [
            // $values = [];
            new Stmt\Expression(new Expr\Assign($valuesVar, new Expr\Array_())),
        ];

        $loopValueVar = new Expr\Variable($uniqueVariableScope->getUniqueName('value'));

        [$output, $itemStatements] = $this->itemTransformer->transform($loopValueVar, $propertyMapping, $uniqueVariableScope);

        if ($this->itemTransformer->assignByRef()) {
            $itemStatements[] = new Stmt\Expression(new Expr\AssignRef(new Expr\ArrayDimFetch($valuesVar), $output));
        } else {
            $itemStatements[] = new Stmt\Expression(new Expr\Assign(new Expr\ArrayDimFetch($valuesVar), $output));
        }

        $statements[] = new Stmt\Foreach_($input, $loopValueVar, [
            'stmts' => $itemStatements,
        ]);

        return [$valuesVar, $statements];
    }

    /**
     * {@inheritdoc}
     */
    public function assignByRef(): bool
    {
        return false;
    }

    /**
     * {@inheritdoc}
     */
    public function getDependencies(): array
    {
        return $this->itemTransformer->getDependencies();
    }
}
