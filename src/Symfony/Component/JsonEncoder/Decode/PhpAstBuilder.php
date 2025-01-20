<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\JsonEncoder\Decode;

use PhpParser\BuilderFactory;
use PhpParser\Node;
use PhpParser\Node\Expr;
use PhpParser\Node\Expr\Array_;
use PhpParser\Node\Expr\ArrayDimFetch;
use PhpParser\Node\Expr\ArrayItem;
use PhpParser\Node\Expr\Assign;
use PhpParser\Node\Expr\BinaryOp\BooleanAnd;
use PhpParser\Node\Expr\BinaryOp\Coalesce;
use PhpParser\Node\Expr\BinaryOp\Identical;
use PhpParser\Node\Expr\BinaryOp\NotIdentical;
use PhpParser\Node\Expr\Cast\Object_ as ObjectCast;
use PhpParser\Node\Expr\Cast\String_ as StringCast;
use PhpParser\Node\Expr\ClassConstFetch;
use PhpParser\Node\Expr\Closure;
use PhpParser\Node\Expr\ClosureUse;
use PhpParser\Node\Expr\Match_;
use PhpParser\Node\Expr\Ternary;
use PhpParser\Node\Expr\Throw_;
use PhpParser\Node\Expr\Yield_;
use PhpParser\Node\Identifier;
use PhpParser\Node\MatchArm;
use PhpParser\Node\Name\FullyQualified;
use PhpParser\Node\Param;
use PhpParser\Node\Stmt;
use PhpParser\Node\Stmt\Expression;
use PhpParser\Node\Stmt\Foreach_;
use PhpParser\Node\Stmt\If_;
use PhpParser\Node\Stmt\Return_;
use Psr\Container\ContainerInterface;
use Symfony\Component\JsonEncoder\DataModel\Decode\BackedEnumNode;
use Symfony\Component\JsonEncoder\DataModel\Decode\CollectionNode;
use Symfony\Component\JsonEncoder\DataModel\Decode\CompositeNode;
use Symfony\Component\JsonEncoder\DataModel\Decode\DataModelNodeInterface;
use Symfony\Component\JsonEncoder\DataModel\Decode\ObjectNode;
use Symfony\Component\JsonEncoder\DataModel\Decode\ScalarNode;
use Symfony\Component\JsonEncoder\DataModel\PhpExprDataAccessor;
use Symfony\Component\JsonEncoder\Exception\LogicException;
use Symfony\Component\JsonEncoder\Exception\UnexpectedValueException;
use Symfony\Component\TypeInfo\Type\BackedEnumType;
use Symfony\Component\TypeInfo\Type\CollectionType;
use Symfony\Component\TypeInfo\Type\ObjectType;
use Symfony\Component\TypeInfo\Type\WrappingTypeInterface;
use Symfony\Component\TypeInfo\TypeIdentifier;

/**
 * Builds a PHP syntax tree that decodes JSON.
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
    public function build(DataModelNodeInterface $dataModel, bool $decodeFromStream, array $options = [], array $context = []): array
    {
        if ($decodeFromStream) {
            return [new Return_(new Closure([
                'static' => true,
                'params' => [
                    new Param($this->builder->var('stream'), type: new Identifier('mixed')),
                    new Param($this->builder->var('denormalizers'), type: new FullyQualified(ContainerInterface::class)),
                    new Param($this->builder->var('instantiator'), type: new FullyQualified(LazyInstantiator::class)),
                    new Param($this->builder->var('options'), type: new Identifier('array')),
                ],
                'returnType' => new Identifier('mixed'),
                'stmts' => [
                    ...$this->buildProvidersStatements($dataModel, $decodeFromStream, $context),
                    new Return_(
                        $this->nodeOnlyNeedsDecode($dataModel, $decodeFromStream)
                        ? $this->builder->staticCall(new FullyQualified(NativeDecoder::class), 'decodeStream', [
                            $this->builder->var('stream'),
                            $this->builder->val(0),
                            $this->builder->val(null),
                        ])
                        : $this->builder->funcCall(new ArrayDimFetch($this->builder->var('providers'), $this->builder->val($dataModel->getIdentifier())), [
                            $this->builder->var('stream'),
                            $this->builder->val(0),
                            $this->builder->val(null),
                        ]),
                    ),
                ],
            ]))];
        }

        return [new Return_(new Closure([
            'static' => true,
            'params' => [
                new Param($this->builder->var('string'), type: new Identifier('string|\\Stringable')),
                new Param($this->builder->var('denormalizers'), type: new FullyQualified(ContainerInterface::class)),
                new Param($this->builder->var('instantiator'), type: new FullyQualified(Instantiator::class)),
                new Param($this->builder->var('options'), type: new Identifier('array')),
            ],
            'returnType' => new Identifier('mixed'),
            'stmts' => [
                ...$this->buildProvidersStatements($dataModel, $decodeFromStream, $context),
                new Return_(
                    $this->nodeOnlyNeedsDecode($dataModel, $decodeFromStream)
                    ? $this->builder->staticCall(new FullyQualified(NativeDecoder::class), 'decodeString', [new StringCast($this->builder->var('string'))])
                    : $this->builder->funcCall(new ArrayDimFetch($this->builder->var('providers'), $this->builder->val($dataModel->getIdentifier())), [
                        $this->builder->staticCall(new FullyQualified(NativeDecoder::class), 'decodeString', [new StringCast($this->builder->var('string'))]),
                    ]),
                ),
            ],
        ]))];
    }

    /**
     * @param array<string, mixed> $context
     *
     * @return list<Stmt>
     */
    private function buildProvidersStatements(DataModelNodeInterface $node, bool $decodeFromStream, array &$context): array
    {
        if ($context['providers'][$node->getIdentifier()] ?? false) {
            return [];
        }

        $context['providers'][$node->getIdentifier()] = true;

        if ($this->nodeOnlyNeedsDecode($node, $decodeFromStream)) {
            return [];
        }

        return match (true) {
            $node instanceof ScalarNode || $node instanceof BackedEnumNode => $this->buildLeafProviderStatements($node, $decodeFromStream),
            $node instanceof CompositeNode => $this->buildCompositeNodeStatements($node, $decodeFromStream, $context),
            $node instanceof CollectionNode => $this->buildCollectionNodeStatements($node, $decodeFromStream, $context),
            $node instanceof ObjectNode => $this->buildObjectNodeStatements($node, $decodeFromStream, $context),
            default => throw new LogicException(\sprintf('Unexpected "%s" data model node', $node::class)),
        };
    }

    /**
     * @return list<Stmt>
     */
    private function buildLeafProviderStatements(ScalarNode|BackedEnumNode $node, bool $decodeFromStream): array
    {
        $accessor = $decodeFromStream
            ? $this->builder->staticCall(new FullyQualified(NativeDecoder::class), 'decodeStream', [
                $this->builder->var('stream'),
                $this->builder->var('offset'),
                $this->builder->var('length'),
            ])
            : $this->builder->var('data');

        $params = $decodeFromStream
            ? [new Param($this->builder->var('stream')), new Param($this->builder->var('offset')), new Param($this->builder->var('length'))]
            : [new Param($this->builder->var('data'))];

        return [
            new Expression(new Assign(
                new ArrayDimFetch($this->builder->var('providers'), $this->builder->val($node->getIdentifier())),
                new Closure([
                    'static' => true,
                    'params' => $params,
                    'stmts' => [new Return_($this->buildFormatValueStatement($node, $accessor))],
                ]),
            )),
        ];
    }

    private function buildFormatValueStatement(DataModelNodeInterface $node, Expr $accessor): Node
    {
        $type = $node->getType();

        if ($node instanceof BackedEnumNode) {
            return $this->builder->staticCall(new FullyQualified($type->getClassName()), 'from', [$accessor]);
        }

        if ($node instanceof ScalarNode) {
            return match (true) {
                TypeIdentifier::NULL === $type->getTypeIdentifier() => $this->builder->val(null),
                TypeIdentifier::OBJECT === $type->getTypeIdentifier() => new ObjectCast($accessor),
                default => $accessor,
            };
        }

        return $accessor;
    }

    /**
     * @param array<string, mixed> $context
     *
     * @return list<Stmt>
     */
    private function buildCompositeNodeStatements(CompositeNode $node, bool $decodeFromStream, array &$context): array
    {
        $prepareDataStmts = $decodeFromStream ? [
            new Expression(new Assign($this->builder->var('data'), $this->builder->staticCall(new FullyQualified(NativeDecoder::class), 'decodeStream', [
                $this->builder->var('stream'),
                $this->builder->var('offset'),
                $this->builder->var('length'),
            ]))),
        ] : [];

        $providersStmts = [];
        $nodesStmts = [];

        $nodeCondition = function (DataModelNodeInterface $node, Expr $accessor): Expr {
            $type = $node->getType();

            if ($type->isIdentifiedBy(TypeIdentifier::NULL)) {
                return new Identical($this->builder->val(null), $this->builder->var('data'));
            }

            if ($type->isIdentifiedBy(TypeIdentifier::TRUE)) {
                return new Identical($this->builder->val(true), $this->builder->var('data'));
            }

            if ($type->isIdentifiedBy(TypeIdentifier::FALSE)) {
                return new Identical($this->builder->val(false), $this->builder->var('data'));
            }

            if ($type->isIdentifiedBy(TypeIdentifier::MIXED)) {
                return $this->builder->val(true);
            }

            if ($type instanceof CollectionType) {
                return $type->isList()
                    ? new BooleanAnd($this->builder->funcCall('\is_array', [$this->builder->var('data')]), $this->builder->funcCall('\array_is_list', [$this->builder->var('data')]))
                    : $this->builder->funcCall('\is_array', [$this->builder->var('data')]);
            }

            while ($type instanceof WrappingTypeInterface) {
                $type = $type->getWrappedType();
            }

            if ($type instanceof BackedEnumType) {
                return $this->builder->funcCall('\is_'.$type->getBackingType()->getTypeIdentifier()->value, [$this->builder->var('data')]);
            }

            if ($type instanceof ObjectType) {
                return $this->builder->funcCall('\is_array', [$this->builder->var('data')]);
            }

            return $this->builder->funcCall('\is_'.$type->getTypeIdentifier()->value, [$this->builder->var('data')]);
        };

        foreach ($node->getNodes() as $n) {
            if ($this->nodeOnlyNeedsDecode($n, $decodeFromStream)) {
                $nodeValueStmt = $this->buildFormatValueStatement($n, $this->builder->var('data'));
            } else {
                $providersStmts = [...$providersStmts, ...$this->buildProvidersStatements($n, $decodeFromStream, $context)];
                $nodeValueStmt = $this->builder->funcCall(
                    new ArrayDimFetch($this->builder->var('providers'), $this->builder->val($n->getIdentifier())),
                    [$this->builder->var('data')],
                );
            }

            $nodesStmts[] = new If_($nodeCondition($n, $this->builder->var('data')), ['stmts' => [new Return_($nodeValueStmt)]]);
        }

        $params = $decodeFromStream
            ? [new Param($this->builder->var('stream')), new Param($this->builder->var('offset')), new Param($this->builder->var('length'))]
            : [new Param($this->builder->var('data'))];

        return [
            ...$providersStmts,
            new Expression(new Assign(
                new ArrayDimFetch($this->builder->var('providers'), $this->builder->val($node->getIdentifier())),
                new Closure([
                    'static' => true,
                    'params' => $params,
                    'uses' => [
                        new ClosureUse($this->builder->var('options')),
                        new ClosureUse($this->builder->var('denormalizers')),
                        new ClosureUse($this->builder->var('instantiator')),
                        new ClosureUse($this->builder->var('providers'), byRef: true),
                    ],
                    'stmts' => [
                        ...$prepareDataStmts,
                        ...$nodesStmts,
                        new Expression(new Throw_($this->builder->new(new FullyQualified(UnexpectedValueException::class), [$this->builder->funcCall('\sprintf', [
                            $this->builder->val(\sprintf('Unexpected "%%s" value for "%s".', $node->getIdentifier())),
                            $this->builder->funcCall('\get_debug_type', [$this->builder->var('data')]),
                        ])]))),
                    ],
                ]),
            )),
        ];
    }

    /**
     * @param array<string, mixed> $context
     *
     * @return list<Stmt>
     */
    private function buildCollectionNodeStatements(CollectionNode $node, bool $decodeFromStream, array &$context): array
    {
        if ($decodeFromStream) {
            $itemValueStmt = $this->nodeOnlyNeedsDecode($node->getItemNode(), $decodeFromStream)
                ? $this->buildFormatValueStatement(
                    $node->getItemNode(),
                    $this->builder->staticCall(new FullyQualified(NativeDecoder::class), 'decodeStream', [
                        $this->builder->var('stream'),
                        new ArrayDimFetch($this->builder->var('v'), $this->builder->val(0)),
                        new ArrayDimFetch($this->builder->var('v'), $this->builder->val(1)),
                    ]),
                )
                : $this->builder->funcCall(
                    new ArrayDimFetch($this->builder->var('providers'), $this->builder->val($node->getItemNode()->getIdentifier())), [
                        $this->builder->var('stream'),
                        new ArrayDimFetch($this->builder->var('v'), $this->builder->val(0)),
                        new ArrayDimFetch($this->builder->var('v'), $this->builder->val(1)),
                    ],
                );
        } else {
            $itemValueStmt = $this->nodeOnlyNeedsDecode($node->getItemNode(), $decodeFromStream)
                ? $this->builder->var('v')
                : $this->builder->funcCall(
                    new ArrayDimFetch($this->builder->var('providers'), $this->builder->val($node->getItemNode()->getIdentifier())),
                    [$this->builder->var('v')],
                );
        }

        $iterableClosureParams = $decodeFromStream
            ? [new Param($this->builder->var('stream')), new Param($this->builder->var('data'))]
            : [new Param($this->builder->var('data'))];

        $iterableClosureStmts = [
            new Expression(new Assign(
                $this->builder->var('iterable'),
                new Closure([
                    'static' => true,
                    'params' => $iterableClosureParams,
                    'uses' => [
                        new ClosureUse($this->builder->var('options')),
                        new ClosureUse($this->builder->var('denormalizers')),
                        new ClosureUse($this->builder->var('instantiator')),
                        new ClosureUse($this->builder->var('providers'), byRef: true),
                    ],
                    'stmts' => [
                        new Foreach_($this->builder->var('data'), $this->builder->var('v'), [
                            'keyVar' => $this->builder->var('k'),
                            'stmts' => [new Expression(new Yield_($itemValueStmt, $this->builder->var('k')))],
                        ]),
                    ],
                ]),
            )),
        ];

        $iterableValueStmt = $decodeFromStream
            ? $this->builder->funcCall($this->builder->var('iterable'), [$this->builder->var('stream'), $this->builder->var('data')])
            : $this->builder->funcCall($this->builder->var('iterable'), [$this->builder->var('data')]);

        $prepareDataStmts = $decodeFromStream ? [
            new Expression(new Assign($this->builder->var('data'), $this->builder->staticCall(
                new FullyQualified(Splitter::class),
                $node->getType()->isList() ? 'splitList' : 'splitDict',
                [$this->builder->var('stream'), $this->builder->var('offset'), $this->builder->var('length')],
            ))),
        ] : [];

        $params = $decodeFromStream
            ? [new Param($this->builder->var('stream')), new Param($this->builder->var('offset')), new Param($this->builder->var('length'))]
            : [new Param($this->builder->var('data'))];

        return [
            new Expression(new Assign(
                new ArrayDimFetch($this->builder->var('providers'), $this->builder->val($node->getIdentifier())),
                new Closure([
                    'static' => true,
                    'params' => $params,
                    'uses' => [
                        new ClosureUse($this->builder->var('options')),
                        new ClosureUse($this->builder->var('denormalizers')),
                        new ClosureUse($this->builder->var('instantiator')),
                        new ClosureUse($this->builder->var('providers'), byRef: true),
                    ],
                    'stmts' => [
                        ...$prepareDataStmts,
                        ...$iterableClosureStmts,
                        new Return_($node->getType()->isIdentifiedBy(TypeIdentifier::ARRAY) ? $this->builder->funcCall('\iterator_to_array', [$iterableValueStmt]) : $iterableValueStmt),
                    ],
                ]),
            )),
            ...($this->nodeOnlyNeedsDecode($node->getItemNode(), $decodeFromStream) ? [] : $this->buildProvidersStatements($node->getItemNode(), $decodeFromStream, $context)),
        ];
    }

    /**
     * @param array<string, mixed> $context
     *
     * @return list<Stmt>
     */
    private function buildObjectNodeStatements(ObjectNode $node, bool $decodeFromStream, array &$context): array
    {
        if ($node->isGhost()) {
            return [];
        }

        $propertyValueProvidersStmts = [];
        $stringPropertiesValuesStmts = [];
        $streamPropertiesValuesStmts = [];

        foreach ($node->getProperties() as $encodedName => $property) {
            $propertyValueProvidersStmts = [
                ...$propertyValueProvidersStmts,
                ...($this->nodeOnlyNeedsDecode($property['value'], $decodeFromStream) ? [] : $this->buildProvidersStatements($property['value'], $decodeFromStream, $context)),
            ];

            if ($decodeFromStream) {
                $propertyValueStmt = $this->nodeOnlyNeedsDecode($property['value'], $decodeFromStream)
                    ? $this->buildFormatValueStatement(
                        $property['value'],
                        $this->builder->staticCall(new FullyQualified(NativeDecoder::class), 'decodeStream', [
                            $this->builder->var('stream'),
                            new ArrayDimFetch($this->builder->var('v'), $this->builder->val(0)),
                            new ArrayDimFetch($this->builder->var('v'), $this->builder->val(1)),
                        ]),
                    )
                    : $this->builder->funcCall(
                        new ArrayDimFetch($this->builder->var('providers'), $this->builder->val($property['value']->getIdentifier())), [
                            $this->builder->var('stream'),
                            new ArrayDimFetch($this->builder->var('v'), $this->builder->val(0)),
                            new ArrayDimFetch($this->builder->var('v'), $this->builder->val(1)),
                        ],
                    );

                $streamPropertiesValuesStmts[] = new MatchArm([$this->builder->val($encodedName)], new Assign(
                    $this->builder->propertyFetch($this->builder->var('object'), $property['name']),
                    $property['accessor'](new PhpExprDataAccessor($propertyValueStmt))->toPhpExpr(),
                ));
            } else {
                $propertyValueStmt = $this->nodeOnlyNeedsDecode($property['value'], $decodeFromStream)
                    ? new Coalesce(new ArrayDimFetch($this->builder->var('data'), $this->builder->val($encodedName)), $this->builder->val('_symfony_missing_value'))
                    : new Ternary(
                        $this->builder->funcCall('\array_key_exists', [$this->builder->val($encodedName), $this->builder->var('data')]),
                        $this->builder->funcCall(
                            new ArrayDimFetch($this->builder->var('providers'), $this->builder->val($property['value']->getIdentifier())),
                            [new ArrayDimFetch($this->builder->var('data'), $this->builder->val($encodedName))],
                        ),
                        $this->builder->val('_symfony_missing_value'),
                    );

                $stringPropertiesValuesStmts[] = new ArrayItem(
                    $property['accessor'](new PhpExprDataAccessor($propertyValueStmt))->toPhpExpr(),
                    $this->builder->val($property['name']),
                );
            }
        }

        $params = $decodeFromStream
            ? [new Param($this->builder->var('stream')), new Param($this->builder->var('offset')), new Param($this->builder->var('length'))]
            : [new Param($this->builder->var('data'))];

        $prepareDataStmts = $decodeFromStream ? [
            new Expression(new Assign($this->builder->var('data'), $this->builder->staticCall(
                new FullyQualified(Splitter::class),
                'splitDict',
                [$this->builder->var('stream'), $this->builder->var('offset'), $this->builder->var('length')],
            ))),
        ] : [];

        if ($decodeFromStream) {
            $instantiateStmts = [
                new Return_($this->builder->methodCall($this->builder->var('instantiator'), 'instantiate', [
                    new ClassConstFetch(new FullyQualified($node->getType()->getClassName()), 'class'),
                    new Closure([
                        'static' => true,
                        'params' => [new Param($this->builder->var('object'))],
                        'uses' => [
                            new ClosureUse($this->builder->var('stream')),
                            new ClosureUse($this->builder->var('data')),
                            new ClosureUse($this->builder->var('options')),
                            new ClosureUse($this->builder->var('denormalizers')),
                            new ClosureUse($this->builder->var('instantiator')),
                            new ClosureUse($this->builder->var('providers'), byRef: true),
                        ],
                        'stmts' => [
                            new Foreach_($this->builder->var('data'), $this->builder->var('v'), [
                                'keyVar' => $this->builder->var('k'),
                                'stmts' => [new Expression(new Match_(
                                    $this->builder->var('k'),
                                    [...$streamPropertiesValuesStmts, new MatchArm(null, $this->builder->val(null))],
                                ))],
                            ]),
                        ],
                    ]),
                ])),
            ];
        } else {
            $instantiateStmts = [
                new Return_($this->builder->methodCall($this->builder->var('instantiator'), 'instantiate', [
                    new ClassConstFetch(new FullyQualified($node->getType()->getClassName()), 'class'),
                    $this->builder->funcCall('\array_filter', [
                        new Array_($stringPropertiesValuesStmts, ['kind' => Array_::KIND_SHORT]),
                        new Closure([
                            'static' => true,
                            'params' => [new Param($this->builder->var('v'))],
                            'stmts' => [new Return_(new NotIdentical($this->builder->val('_symfony_missing_value'), $this->builder->var('v')))],
                        ]),
                    ]),
                ])),
            ];
        }

        return [
            new Expression(new Assign(
                new ArrayDimFetch($this->builder->var('providers'), $this->builder->val($node->getIdentifier())),
                new Closure([
                    'static' => true,
                    'params' => $params,
                    'uses' => [
                        new ClosureUse($this->builder->var('options')),
                        new ClosureUse($this->builder->var('denormalizers')),
                        new ClosureUse($this->builder->var('instantiator')),
                        new ClosureUse($this->builder->var('providers'), byRef: true),
                    ],
                    'stmts' => [
                        ...$prepareDataStmts,
                        ...$instantiateStmts,
                    ],
                ]),
            )),
            ...$propertyValueProvidersStmts,
        ];
    }

    private function nodeOnlyNeedsDecode(DataModelNodeInterface $node, bool $decodeFromStream): bool
    {
        if ($node instanceof CompositeNode) {
            foreach ($node->getNodes() as $n) {
                if (!$this->nodeOnlyNeedsDecode($n, $decodeFromStream)) {
                    return false;
                }
            }

            return true;
        }

        if ($node instanceof CollectionNode) {
            if ($decodeFromStream) {
                return false;
            }

            return $this->nodeOnlyNeedsDecode($node->getItemNode(), $decodeFromStream);
        }

        if ($node instanceof ObjectNode) {
            return false;
        }

        if ($node instanceof BackedEnumNode) {
            return false;
        }

        if ($node instanceof ScalarNode) {
            return !$node->getType()->isIdentifiedBy(TypeIdentifier::OBJECT);
        }

        return true;
    }
}
