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

use PhpParser\Node\Arg;
use PhpParser\Node\Expr;
use PhpParser\Node\Name;
use PhpParser\Node\Stmt;
use Symfony\Component\AutoMapper\Extractor\PropertyMapping;
use Symfony\Component\AutoMapper\Generator\UniqueVariableScope;
use Symfony\Component\PropertyInfo\Type;

/**
 * Multiple transformer decorator.
 *
 * Decorate transformers with condition to handle property with multiples source types
 * It will always use the first target type possible for transformation
 *
 * @expiremental in 4.3
 *
 * @author Joel Wurtz <jwurtz@jolicode.com>
 */
final class MultipleTransformer implements TransformerInterface
{
    private const CONDITION_MAPPING = [
        Type::BUILTIN_TYPE_BOOL => 'is_bool',
        Type::BUILTIN_TYPE_INT => 'is_int',
        Type::BUILTIN_TYPE_FLOAT => 'is_float',
        Type::BUILTIN_TYPE_STRING => 'is_string',
        Type::BUILTIN_TYPE_NULL => 'is_null',
        Type::BUILTIN_TYPE_ARRAY => 'is_array',
        Type::BUILTIN_TYPE_OBJECT => 'is_object',
        Type::BUILTIN_TYPE_RESOURCE => 'is_resource',
        Type::BUILTIN_TYPE_CALLABLE => 'is_callable',
        Type::BUILTIN_TYPE_ITERABLE => 'is_iterable',
    ];

    private $transformers = [];

    public function addTransformer(TransformerInterface $transformer, Type $sourceType)
    {
        $this->transformers[] = [
            'transformer' => $transformer,
            'type' => $sourceType,
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function transform(Expr $input, PropertyMapping $propertyMapping, UniqueVariableScope $uniqueVariableScope): array
    {
        $output = new Expr\Variable($uniqueVariableScope->getUniqueName('value'));
        $statements = [
            new Stmt\Expression(new Expr\Assign($output, $input)),
        ];

        foreach ($this->transformers as $transformerData) {
            $transformer = $transformerData['transformer'];
            $type = $transformerData['type'];

            [$transformerOutput, $transformerStatements] = $transformer->transform($input, $propertyMapping, $uniqueVariableScope);

            $assignClass = $transformer->assignByRef() ? Expr\AssignRef::class : Expr\Assign::class;
            $statements[] = new Stmt\If_(
                new Expr\FuncCall(
                    new Name(self::CONDITION_MAPPING[$type->getBuiltinType()]),
                    [
                        new Arg($input),
                    ]
                ),
                [
                    'stmts' => array_merge(
                        $transformerStatements, [
                            new Stmt\Expression(new $assignClass($output, $transformerOutput)),
                        ]
                    ),
                ]
            );
        }

        return [$output, $statements];
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
        $dependencies = [];

        foreach ($this->transformers as $transformerData) {
            $dependencies = array_merge($dependencies, $transformerData['transformer']->getDependencies());
        }

        return $dependencies;
    }
}
