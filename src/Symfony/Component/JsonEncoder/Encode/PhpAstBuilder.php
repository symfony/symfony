<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\JsonEncoder\Encode;

use PhpParser\BuilderFactory;
use PhpParser\Node\Expr;
use PhpParser\Node\Expr\Assign;
use PhpParser\Node\Expr\BinaryOp\Identical;
use PhpParser\Node\Expr\Closure;
use PhpParser\Node\Expr\Instanceof_;
use PhpParser\Node\Expr\PropertyFetch;
use PhpParser\Node\Expr\Ternary;
use PhpParser\Node\Expr\Throw_;
use PhpParser\Node\Expr\Yield_;
use PhpParser\Node\Identifier;
use PhpParser\Node\Name\FullyQualified;
use PhpParser\Node\Param;
use PhpParser\Node\Scalar\Encapsed;
use PhpParser\Node\Scalar\EncapsedStringPart;
use PhpParser\Node\Stmt;
use PhpParser\Node\Stmt\Else_;
use PhpParser\Node\Stmt\ElseIf_;
use PhpParser\Node\Stmt\Expression;
use PhpParser\Node\Stmt\Foreach_;
use PhpParser\Node\Stmt\If_;
use PhpParser\Node\Stmt\Return_;
use Psr\Container\ContainerInterface;
use Symfony\Component\JsonEncoder\DataModel\Encode\BackedEnumNode;
use Symfony\Component\JsonEncoder\DataModel\Encode\CollectionNode;
use Symfony\Component\JsonEncoder\DataModel\Encode\CompositeNode;
use Symfony\Component\JsonEncoder\DataModel\Encode\DataModelNodeInterface;
use Symfony\Component\JsonEncoder\DataModel\Encode\ExceptionNode;
use Symfony\Component\JsonEncoder\DataModel\Encode\ObjectNode;
use Symfony\Component\JsonEncoder\DataModel\Encode\ScalarNode;
use Symfony\Component\JsonEncoder\Exception\LogicException;
use Symfony\Component\JsonEncoder\Exception\RuntimeException;
use Symfony\Component\JsonEncoder\Exception\UnexpectedValueException;
use Symfony\Component\TypeInfo\Type\ObjectType;
use Symfony\Component\TypeInfo\TypeIdentifier;

/**
 * Builds a PHP syntax tree that encodes data to JSON.
 *
 * @author Mathias Arlaud <mathias.arlaud@gmail.com>
 *
 * @internal
 */
final class PhpAstBuilder
{
    private BuilderFactory $builder;

    public function __construct(
        private bool $forceEncodeChunks = false,
    ) {
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
        $closureStmts = $this->buildClosureStatements($dataModel, $options, $context);

        return [new Return_(new Closure([
            'static' => true,
            'params' => [
                new Param($this->builder->var('data'), type: new Identifier('mixed')),
                new Param($this->builder->var('normalizers'), type: new FullyQualified(ContainerInterface::class)),
                new Param($this->builder->var('options'), type: new Identifier('array')),
            ],
            'returnType' => new FullyQualified(\Traversable::class),
            'stmts' => $closureStmts,
        ]))];
    }

    /**
     * @param array<string, mixed> $options
     * @param array<string, mixed> $context
     *
     * @return list<Stmt>
     */
    private function buildClosureStatements(DataModelNodeInterface $dataModelNode, array $options, array $context): array
    {
        $accessor = $dataModelNode->getAccessor()->toPhpExpr();

        if ($dataModelNode instanceof ExceptionNode) {
            return [
                new Expression(new Throw_($accessor)),
            ];
        }

        if (!$this->forceEncodeChunks && $this->nodeOnlyNeedsEncode($dataModelNode)) {
            return [
                new Expression(new Yield_($this->encodeValue($accessor))),
            ];
        }

        if ($dataModelNode instanceof ScalarNode) {
            $scalarAccessor = match (true) {
                TypeIdentifier::NULL === $dataModelNode->getType()->getTypeIdentifier() => $this->builder->val('null'),
                TypeIdentifier::BOOL === $dataModelNode->getType()->getTypeIdentifier() => new Ternary($accessor, $this->builder->val('true'), $this->builder->val('false')),
                default => $this->encodeValue($accessor),
            };

            return [
                new Expression(new Yield_($scalarAccessor)),
            ];
        }

        if ($dataModelNode instanceof BackedEnumNode) {
            return [
                new Expression(new Yield_($this->encodeValue(new PropertyFetch($accessor, 'value')))),
            ];
        }

        if ($dataModelNode instanceof CompositeNode) {
            $nodeCondition = function (DataModelNodeInterface $node): Expr {
                $accessor = $node->getAccessor()->toPhpExpr();
                $type = $node->getType()->getBaseType();

                return match (true) {
                    $type instanceof ObjectType => new Instanceof_($accessor, new FullyQualified($type->getClassName())),
                    TypeIdentifier::NULL === $type->getTypeIdentifier() || TypeIdentifier::NEVER === $type->getTypeIdentifier() || TypeIdentifier::VOID === $type->getTypeIdentifier() => new Identical($this->builder->val(null), $accessor),
                    TypeIdentifier::TRUE === $type->getTypeIdentifier() => new Identical($this->builder->val(true), $accessor),
                    TypeIdentifier::FALSE === $type->getTypeIdentifier() => new Identical($this->builder->val(false), $accessor),
                    TypeIdentifier::MIXED === $type->getTypeIdentifier() => $this->builder->val(true),
                    default => $this->builder->funcCall('\is_'.$type->getTypeIdentifier()->value, [$accessor]),
                };
            };

            $stmtsAndConditions = array_map(fn (DataModelNodeInterface $n): array => [
                'condition' => $nodeCondition($n),
                'stmts' => $this->buildClosureStatements($n, $options, $context),
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
            if ($dataModelNode->getType()->isList()) {
                return [
                    new Expression(new Yield_($this->builder->val('['))),
                    new Expression(new Assign($this->builder->var('prefix'), $this->builder->val(''))),
                    new Foreach_($accessor, $dataModelNode->getItemNode()->getAccessor()->toPhpExpr(), [
                        'stmts' => [
                            new Expression(new Yield_($this->builder->var('prefix'))),
                            ...$this->buildClosureStatements($dataModelNode->getItemNode(), $options, $context),
                            new Expression(new Assign($this->builder->var('prefix'), $this->builder->val(','))),
                        ],
                    ]),
                    new Expression(new Yield_($this->builder->val(']'))),
                ];
            }

            return [
                new Expression(new Yield_($this->builder->val('{'))),
                new Expression(new Assign($this->builder->var('prefix'), $this->builder->val(''))),
                new Foreach_($accessor, $dataModelNode->getItemNode()->getAccessor()->toPhpExpr(), [
                    'keyVar' => $this->builder->var('key'),
                    'stmts' => [
                        new Expression(new Assign($this->builder->var('key'), $this->escapeString($this->builder->var('key')))),
                        new Expression(new Yield_(new Encapsed([
                            $this->builder->var('prefix'),
                            new EncapsedStringPart('"'),
                            $this->builder->var('key'),
                            new EncapsedStringPart('":'),
                        ]))),
                        ...$this->buildClosureStatements($dataModelNode->getItemNode(), $options, $context),
                        new Expression(new Assign($this->builder->var('prefix'), $this->builder->val(','))),
                    ],
                ]),
                new Expression(new Yield_($this->builder->val('}'))),
            ];
        }

        if ($dataModelNode instanceof ObjectNode) {
            $objectStmts = [new Expression(new Yield_($this->builder->val('{')))];
            $separator = '';

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
                    ...$this->buildClosureStatements($propertyNode, $options, $context),
                ];

                $separator = ',';
            }

            $objectStmts[] = new Expression(new Yield_($this->builder->val('}')));

            return $objectStmts;
        }

        throw new LogicException(\sprintf('Unexpected "%s" node', $dataModelNode::class));
    }

    private function encodeValue(Expr $value): Expr
    {
        return $this->builder->funcCall('\json_encode', [$value]);
    }

    private function escapeString(Expr $string): Expr
    {
        return $this->builder->funcCall('\substr', [$this->encodeValue($string), $this->builder->val(1), $this->builder->val(-1)]);
    }

    private function nodeOnlyNeedsEncode(DataModelNodeInterface $node, int $nestingLevel = 0): bool
    {
        if ($node instanceof CompositeNode) {
            foreach ($node->getNodes() as $n) {
                if (!$this->nodeOnlyNeedsEncode($n, $nestingLevel + 1)) {
                    return false;
                }
            }

            return true;
        }

        if ($node instanceof CollectionNode) {
            return $this->nodeOnlyNeedsEncode($node->getItemNode(), $nestingLevel + 1);
        }

        if ($node instanceof ObjectNode && !$node->isTransformed()) {
            foreach ($node->getProperties() as $property) {
                if (!$this->nodeOnlyNeedsEncode($property, $nestingLevel + 1)) {
                    return false;
                }
            }

            return true;
        }

        if ($node instanceof ScalarNode) {
            $type = $node->getType();

            // "null" will be written directly using the "null" string
            // "bool" will be written directly using the "true" or "false" string
            if ($type->isA(TypeIdentifier::NULL) || $type->isA(TypeIdentifier::BOOL)) {
                return $nestingLevel > 0;
            }

            return true;
        }

        if ($node instanceof ExceptionNode) {
            return true;
        }

        return false;
    }
}
