<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\JsonStreamer\Write;

use PhpParser\BuilderFactory;
use PhpParser\Node\ClosureUse;
use PhpParser\Node\Expr;
use PhpParser\Node\Expr\ArrayDimFetch;
use PhpParser\Node\Expr\Assign;
use PhpParser\Node\Expr\BinaryOp\GreaterOrEqual;
use PhpParser\Node\Expr\BinaryOp\Identical;
use PhpParser\Node\Expr\BinaryOp\Plus;
use PhpParser\Node\Expr\Closure;
use PhpParser\Node\Expr\Instanceof_;
use PhpParser\Node\Expr\PropertyFetch;
use PhpParser\Node\Expr\Ternary;
use PhpParser\Node\Expr\Throw_;
use PhpParser\Node\Expr\Yield_;
use PhpParser\Node\Expr\YieldFrom;
use PhpParser\Node\Identifier;
use PhpParser\Node\Name\FullyQualified;
use PhpParser\Node\Param;
use PhpParser\Node\Scalar\Encapsed;
use PhpParser\Node\Scalar\EncapsedStringPart;
use PhpParser\Node\Stmt;
use PhpParser\Node\Stmt\Catch_;
use PhpParser\Node\Stmt\Else_;
use PhpParser\Node\Stmt\ElseIf_;
use PhpParser\Node\Stmt\Expression;
use PhpParser\Node\Stmt\Foreach_;
use PhpParser\Node\Stmt\If_;
use PhpParser\Node\Stmt\Return_;
use PhpParser\Node\Stmt\TryCatch;
use Psr\Container\ContainerInterface;
use Symfony\Component\JsonStreamer\DataModel\VariableDataAccessor;
use Symfony\Component\JsonStreamer\DataModel\Write\BackedEnumNode;
use Symfony\Component\JsonStreamer\DataModel\Write\CollectionNode;
use Symfony\Component\JsonStreamer\DataModel\Write\CompositeNode;
use Symfony\Component\JsonStreamer\DataModel\Write\DataModelNodeInterface;
use Symfony\Component\JsonStreamer\DataModel\Write\ObjectNode;
use Symfony\Component\JsonStreamer\DataModel\Write\ScalarNode;
use Symfony\Component\JsonStreamer\Exception\LogicException;
use Symfony\Component\JsonStreamer\Exception\NotEncodableValueException;
use Symfony\Component\JsonStreamer\Exception\RuntimeException;
use Symfony\Component\JsonStreamer\Exception\UnexpectedValueException;
use Symfony\Component\TypeInfo\Type\BuiltinType;
use Symfony\Component\TypeInfo\Type\ObjectType;
use Symfony\Component\TypeInfo\Type\WrappingTypeInterface;
use Symfony\Component\TypeInfo\TypeIdentifier;

/**
 * Builds a PHP syntax tree that writes data to JSON stream.
 *
 * @author Mathias Arlaud <mathias.arlaud@gmail.com>
 *
 * @internal
 */
final class PhpAstBuilder
{
    private BuilderFactory $builder;

    public function __construct()
    {
        $this->builder = new BuilderFactory();
    }

    /**
     * @param array<string, mixed> $options
     * @param array<string, mixed> $context
     *
     * @return list<Stmt>
     */
    public function build(DataModelNodeInterface $dataModel, array $options = [], array $context = []): array
    {
        $context['depth'] = 0;

        $generatorStmts = $this->buildGeneratorStatementsByIdentifiers($dataModel, $options, $context);

        // filter generators to mock only
        $generatorStmts = array_merge(...array_values(array_intersect_key($generatorStmts, $context['mocks'] ?? [])));
        $context['generators'] = array_intersect_key($context['generators'] ?? [], $context['mocks'] ?? []);

        return [new Return_(new Closure([
            'static' => true,
            'params' => [
                new Param($this->builder->var('data'), type: new Identifier('mixed')),
                new Param($this->builder->var('valueTransformers'), type: new FullyQualified(ContainerInterface::class)),
                new Param($this->builder->var('options'), type: new Identifier('array')),
            ],
            'returnType' => new FullyQualified(\Traversable::class),
            'stmts' => [
                ...$generatorStmts,
                new TryCatch(
                    $this->buildYieldStatements($dataModel, $options, $context),
                    [new Catch_([new FullyQualified(\JsonException::class)], $this->builder->var('e'), [
                        new Expression(new Throw_($this->builder->new(new FullyQualified(NotEncodableValueException::class), [
                            $this->builder->methodCall($this->builder->var('e'), 'getMessage'),
                            $this->builder->val(0),
                            $this->builder->var('e'),
                        ]))),
                    ])]
                ),
            ],
        ]))];
    }

    /**
     * @param array<string, mixed> $options
     * @param array<string, mixed> $context
     *
     * @return array<string, list<Stmt>>
     */
    private function buildGeneratorStatementsByIdentifiers(DataModelNodeInterface $node, array $options, array &$context): array
    {
        if ($context['generators'][$node->getIdentifier()] ?? false) {
            return [];
        }

        if ($node instanceof CollectionNode) {
            return $this->buildGeneratorStatementsByIdentifiers($node->getItemNode(), $options, $context);
        }

        if ($node instanceof CompositeNode) {
            $stmts = [];

            foreach ($node->getNodes() as $n) {
                $stmts = [
                    ...$stmts,
                    ...$this->buildGeneratorStatementsByIdentifiers($n, $options, $context),
                ];
            }

            return $stmts;
        }

        if (!$node instanceof ObjectNode) {
            return [];
        }

        if ($node->isMock()) {
            $context['mocks'][$node->getIdentifier()] = true;

            return [];
        }

        $context['building_generator'] = true;

        $stmts = [
            $node->getIdentifier() => [
                new Expression(new Assign(
                    new ArrayDimFetch($this->builder->var('generators'), $this->builder->val($node->getIdentifier())),
                    new Closure([
                        'static' => true,
                        'params' => [
                            new Param($this->builder->var('data')),
                            new Param($this->builder->var('depth')),
                        ],
                        'uses' => [
                            new ClosureUse($this->builder->var('valueTransformers')),
                            new ClosureUse($this->builder->var('options')),
                            new ClosureUse($this->builder->var('generators'), byRef: true),
                        ],
                        'stmts' => [
                            new If_(new GreaterOrEqual($this->builder->var('depth'), $this->builder->val(512)), [
                                'stmts' => [new Expression(new Throw_($this->builder->new(new FullyQualified(NotEncodableValueException::class), [$this->builder->val('Maximum stack depth exceeded')])))],
                            ]),
                            ...$this->buildYieldStatements($node->withAccessor(new VariableDataAccessor('data')), $options, $context),
                        ],
                    ]),
                )),
            ],
        ];

        foreach ($node->getProperties() as $n) {
            $stmts = [
                ...$stmts,
                ...$this->buildGeneratorStatementsByIdentifiers($n, $options, $context),
            ];
        }

        unset($context['building_generator']);
        $context['generators'][$node->getIdentifier()] = true;

        return $stmts;
    }

    /**
     * @param array<string, mixed> $options
     * @param array<string, mixed> $context
     *
     * @return list<Stmt>
     */
    private function buildYieldStatements(DataModelNodeInterface $dataModelNode, array $options, array $context): array
    {
        $accessor = $dataModelNode->getAccessor()->toPhpExpr();

        if ($this->dataModelOnlyNeedsEncode($dataModelNode)) {
            return [
                new Expression(new Yield_($this->encodeValue($accessor, $context))),
            ];
        }

        if ($context['depth'] >= 512) {
            return [
                new Expression(new Throw_($this->builder->new(new FullyQualified(NotEncodableValueException::class), [$this->builder->val('Maximum stack depth exceeded')]))),
            ];
        }

        if ($dataModelNode instanceof ScalarNode) {
            $scalarAccessor = match (true) {
                TypeIdentifier::NULL === $dataModelNode->getType()->getTypeIdentifier() => $this->builder->val('null'),
                TypeIdentifier::BOOL === $dataModelNode->getType()->getTypeIdentifier() => new Ternary($accessor, $this->builder->val('true'), $this->builder->val('false')),
                default => $this->encodeValue($accessor, $context),
            };

            return [
                new Expression(new Yield_($scalarAccessor)),
            ];
        }

        if ($dataModelNode instanceof BackedEnumNode) {
            return [
                new Expression(new Yield_($this->encodeValue(new PropertyFetch($accessor, 'value'), $context))),
            ];
        }

        if ($dataModelNode instanceof CompositeNode) {
            $nodeCondition = function (DataModelNodeInterface $node): Expr {
                $accessor = $node->getAccessor()->toPhpExpr();
                $type = $node->getType();

                if ($type->isIdentifiedBy(TypeIdentifier::NULL, TypeIdentifier::NEVER, TypeIdentifier::VOID)) {
                    return new Identical($this->builder->val(null), $accessor);
                }

                if ($type->isIdentifiedBy(TypeIdentifier::TRUE)) {
                    return new Identical($this->builder->val(true), $accessor);
                }

                if ($type->isIdentifiedBy(TypeIdentifier::FALSE)) {
                    return new Identical($this->builder->val(false), $accessor);
                }

                if ($type->isIdentifiedBy(TypeIdentifier::MIXED)) {
                    return $this->builder->val(true);
                }

                while ($type instanceof WrappingTypeInterface) {
                    $type = $type->getWrappedType();
                }

                if ($type instanceof ObjectType) {
                    return new Instanceof_($accessor, new FullyQualified($type->getClassName()));
                }

                if ($type instanceof BuiltinType) {
                    return $this->builder->funcCall('\is_'.$type->getTypeIdentifier()->value, [$accessor]);
                }

                throw new LogicException(\sprintf('Unexpected "%s" type.', $type::class));
            };

            $stmtsAndConditions = array_map(fn (DataModelNodeInterface $n): array => [
                'condition' => $nodeCondition($n),
                'stmts' => $this->buildYieldStatements($n, $options, $context),
            ], $dataModelNode->getNodes());

            $if = $stmtsAndConditions[0];
            unset($stmtsAndConditions[0]);

            return [
                new If_($if['condition'], [
                    'stmts' => $if['stmts'],
                    'elseifs' => array_map(fn (array $s): ElseIf_ => new ElseIf_($s['condition'], $s['stmts']), $stmtsAndConditions),
                    'else' => new Else_([
                        new Expression(new Throw_($this->builder->new(new FullyQualified(UnexpectedValueException::class), [$this->builder->funcCall('\sprintf', [
                            $this->builder->val('Unexpected "%s" value.'),
                            $this->builder->funcCall('\get_debug_type', [$accessor]),
                        ])]))),
                    ]),
                ]),
            ];
        }

        if ($dataModelNode instanceof CollectionNode) {
            ++$context['depth'];

            if ($dataModelNode->getType()->isList()) {
                return [
                    new Expression(new Yield_($this->builder->val('['))),
                    new Expression(new Assign($this->builder->var('prefix'), $this->builder->val(''))),
                    new Foreach_($accessor, $dataModelNode->getItemNode()->getAccessor()->toPhpExpr(), [
                        'stmts' => [
                            new Expression(new Yield_($this->builder->var('prefix'))),
                            ...$this->buildYieldStatements($dataModelNode->getItemNode(), $options, $context),
                            new Expression(new Assign($this->builder->var('prefix'), $this->builder->val(','))),
                        ],
                    ]),
                    new Expression(new Yield_($this->builder->val(']'))),
                ];
            }

            $escapedKey = $dataModelNode->getType()->getCollectionKeyType()->isIdentifiedBy(TypeIdentifier::INT)
                ? new Ternary($this->builder->funcCall('is_int', [$this->builder->var('key')]), $this->builder->var('key'), $this->escapeString($this->builder->var('key')))
                : $this->escapeString($this->builder->var('key'));

            return [
                new Expression(new Yield_($this->builder->val('{'))),
                new Expression(new Assign($this->builder->var('prefix'), $this->builder->val(''))),
                new Foreach_($accessor, $dataModelNode->getItemNode()->getAccessor()->toPhpExpr(), [
                    'keyVar' => $this->builder->var('key'),
                    'stmts' => [
                        new Expression(new Assign($this->builder->var('key'), $escapedKey)),
                        new Expression(new Yield_(new Encapsed([
                            $this->builder->var('prefix'),
                            new EncapsedStringPart('"'),
                            $this->builder->var('key'),
                            new EncapsedStringPart('":'),
                        ]))),
                        ...$this->buildYieldStatements($dataModelNode->getItemNode(), $options, $context),
                        new Expression(new Assign($this->builder->var('prefix'), $this->builder->val(','))),
                    ],
                ]),
                new Expression(new Yield_($this->builder->val('}'))),
            ];
        }

        if ($dataModelNode instanceof ObjectNode) {
            if (isset($context['generators'][$dataModelNode->getIdentifier()]) || $dataModelNode->isMock()) {
                $depthArgument = ($context['building_generator'] ?? false)
                    ? new Plus($this->builder->var('depth'), $this->builder->val(1))
                    : $this->builder->val($context['depth']);

                return [
                    new Expression(new YieldFrom($this->builder->funcCall(
                        new ArrayDimFetch($this->builder->var('generators'), $this->builder->val($dataModelNode->getIdentifier())),
                        [$accessor, $depthArgument],
                    ))),
                ];
            }

            $objectStmts = [new Expression(new Yield_($this->builder->val('{')))];
            $separator = '';

            ++$context['depth'];

            foreach ($dataModelNode->getProperties() as $name => $propertyNode) {
                $encodedName = json_encode($name);
                if (false === $encodedName) {
                    throw new RuntimeException(\sprintf('Cannot encode "%s"', $name));
                }

                $encodedName = substr($encodedName, 1, -1);

                $objectStmts = [
                    ...$objectStmts,
                    new Expression(new Yield_($this->builder->val($separator))),
                    new Expression(new Yield_($this->builder->val('"'))),
                    new Expression(new Yield_($this->builder->val($encodedName))),
                    new Expression(new Yield_($this->builder->val('":'))),
                    ...$this->buildYieldStatements($propertyNode, $options, $context),
                ];

                $separator = ',';
            }

            $objectStmts[] = new Expression(new Yield_($this->builder->val('}')));

            return $objectStmts;
        }

        throw new LogicException(\sprintf('Unexpected "%s" node', $dataModelNode::class));
    }

    /**
     * @param array<string, mixed> $context
     */
    private function encodeValue(Expr $value, array $context): Expr
    {
        return $this->builder->funcCall('\json_encode', [
            $value,
            $this->builder->constFetch('\\JSON_THROW_ON_ERROR'),
            $this->builder->val(512 - $context['depth']),
        ]);
    }

    private function escapeString(Expr $string): Expr
    {
        return $this->builder->funcCall('\substr', [
            $this->builder->funcCall('\json_encode', [$string]),
            $this->builder->val(1),
            $this->builder->val(-1),
        ]);
    }

    private function dataModelOnlyNeedsEncode(DataModelNodeInterface $dataModel, int $depth = 0): bool
    {
        if ($dataModel instanceof CompositeNode) {
            foreach ($dataModel->getNodes() as $node) {
                if (!$this->dataModelOnlyNeedsEncode($node, $depth)) {
                    return false;
                }
            }

            return true;
        }

        if ($dataModel instanceof CollectionNode) {
            return $this->dataModelOnlyNeedsEncode($dataModel->getItemNode(), $depth + 1);
        }

        if (!$dataModel instanceof ScalarNode) {
            return false;
        }

        $type = $dataModel->getType();

        // "null" will be written directly using the "null" string
        // "bool" will be written directly using the "true" or "false" string
        // but it must not prevent any json_encode if nested
        if ($type->isIdentifiedBy(TypeIdentifier::NULL) || $type->isIdentifiedBy(TypeIdentifier::BOOL)) {
            return $depth > 0;
        }

        return true;
    }
}
