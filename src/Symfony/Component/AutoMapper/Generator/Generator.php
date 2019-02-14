<?php

namespace Symfony\Component\AutoMapper\Generator;

use PhpParser\Node\Arg;
use PhpParser\Node\Expr;
use PhpParser\Node\Name;
use PhpParser\Node\Param;
use PhpParser\Node\Scalar;
use PhpParser\Node\Stmt;
use PhpParser\Parser;
use PhpParser\ParserFactory;
use Symfony\Component\AutoMapper\AutoMapperRegistryInterface;
use Symfony\Component\AutoMapper\Context;
use Symfony\Component\AutoMapper\Exception\CompileException;
use Symfony\Component\AutoMapper\Extractor\PropertyMapping;
use Symfony\Component\AutoMapper\GeneratedMapper;
use Symfony\Component\AutoMapper\MapperGeneratorMetadataInterface;
use Symfony\Component\Serializer\Mapping\ClassDiscriminatorMapping;
use Symfony\Component\Serializer\Mapping\ClassDiscriminatorResolverInterface;

/**
 * Generate code for a mapping class.
 */
class Generator
{
    private $parser;

    private $classDiscriminator;

    public function __construct(Parser $parser = null, ClassDiscriminatorResolverInterface $classDiscriminator = null)
    {
        $this->parser = $parser ?? (new ParserFactory())->create(ParserFactory::PREFER_PHP7);
        $this->classDiscriminator = $classDiscriminator;
    }

    /**
     * Generate Class AST given metadata for a mapper.
     *
     * @throws CompileException
     */
    public function generate(MapperGeneratorMetadataInterface $mapperGeneratorMetadata): Stmt\Class_
    {
        $propertiesMapping = $mapperGeneratorMetadata->getPropertiesMapping();

        $uniqueVariableScope = new UniqueVariableScope();
        $sourceInput = new Expr\Variable($uniqueVariableScope->getUniqueName('value'));
        $result = new Expr\Variable($uniqueVariableScope->getUniqueName('result'));
        $hashVariable = new Expr\Variable($uniqueVariableScope->getUniqueName('sourceHash'));
        $contextVariable = new Expr\Variable($uniqueVariableScope->getUniqueName('context'));
        $constructStatements = [];
        $addedDependencies = [];
        $canHaveCircularDependency = $mapperGeneratorMetadata->canHaveCircularReference() && 'array' !== $mapperGeneratorMetadata->getSource();

        $statements = [
            new Stmt\If_(new Expr\BinaryOp\Identical(new Expr\ConstFetch(new Name('null')), $sourceInput), [
                'stmts' => [new Stmt\Return_($sourceInput)],
            ]),
        ];

        if ($canHaveCircularDependency) {
            $statements[] = new Stmt\Expression(new Expr\Assign($hashVariable, new Expr\BinaryOp\Concat(new Expr\FuncCall(new Name('spl_object_hash'), [
                new Arg($sourceInput),
            ]),
                new Scalar\String_($mapperGeneratorMetadata->getTarget())
            )));
            $statements[] = new Stmt\If_(new Expr\MethodCall($contextVariable, new Name('shouldHandleCircularReference'), [
                new Arg($hashVariable),
                new Arg(new Expr\PropertyFetch(new Expr\Variable('this'), 'circularReferenceLimit')),
            ]), [
                'stmts' => [
                    new Stmt\Return_(new Expr\MethodCall($contextVariable, 'handleCircularReference', [
                        new Arg($hashVariable),
                        new Arg($sourceInput),
                        new Arg(new Expr\PropertyFetch(new Expr\Variable('this'), 'circularReferenceLimit')),
                        new Arg(new Expr\PropertyFetch(new Expr\Variable('this'), 'circularReferenceHandler')),
                    ])),
                ],
            ]);
        }

        [$createObjectStmts, $inConstructor, $constructStatementsForCreateObjects, $injectMapperStatements] = $this->getCreateObjectStatements($mapperGeneratorMetadata, $result, $contextVariable, $sourceInput, $uniqueVariableScope);
        $constructStatements = array_merge($constructStatements, $constructStatementsForCreateObjects);

        $statements[] = new Stmt\Expression(new Expr\Assign($result, new Expr\MethodCall($contextVariable, 'getObjectToPopulate')));
        $statements[] = new Stmt\If_(new Expr\BinaryOp\Identical(new Expr\ConstFetch(new Name('null')), $result), [
            'stmts' => $createObjectStmts,
        ]);

        /** @var PropertyMapping $propertyMapping */
        foreach ($propertiesMapping as $propertyMapping) {
            $transformer = $propertyMapping->getTransformer();

            /* @var PropertyMapping $propertyMapping */
            foreach ($transformer->getDependencies() as $dependency) {
                if (isset($addedDependencies[$dependency->getName()])) {
                    continue;
                }

                $injectMapperStatements[] = new Stmt\Expression(new Expr\Assign(
                    new Expr\ArrayDimFetch(new Expr\PropertyFetch(new Expr\Variable('this'), 'mappers'), new Scalar\String_($dependency->getName())),
                    new Expr\MethodCall(new Expr\Variable('autoMapperRegistry'), 'getMapper', [
                        new Arg(new Scalar\String_($dependency->getSource())),
                        new Arg(new Scalar\String_($dependency->getTarget())),
                    ])
                ));
                $addedDependencies[$dependency->getName()] = true;
            }
        }

        if (\count($addedDependencies) > 0) {
            if ($canHaveCircularDependency) {
                $statements[] = new Stmt\Expression(new Expr\Assign(
                    $contextVariable,
                    new Expr\MethodCall($contextVariable, 'withReference', [
                        new Arg($hashVariable),
                        new Arg($result),
                    ])
                ));
            }

            $statements[] = new Stmt\Expression(new Expr\Assign(
                $contextVariable,
                new Expr\MethodCall($contextVariable, 'withIncrementedDepth')
            ));
        }

        /** @var PropertyMapping $propertyMapping */
        foreach ($propertiesMapping as $propertyMapping) {
            $transformer = $propertyMapping->getTransformer();

            if (\in_array($propertyMapping->getProperty(), $inConstructor, true)) {
                continue;
            }

            [$output, $propStatements] = $transformer->transform($propertyMapping->getReadAccessor()->getExpression($sourceInput), $propertyMapping, $uniqueVariableScope);
            $writeExpression = $propertyMapping->getWriteMutator()->getExpression($result, $output, $transformer->assignByRef());

            if (null === $writeExpression) {
                continue;
            }

            $propStatements[] = new Stmt\Expression($writeExpression);
            $conditions = [];

            $extractCallback = $propertyMapping->getReadAccessor()->getExtractCallback($mapperGeneratorMetadata->getSource());
            $hydrateCallback = $propertyMapping->getWriteMutator()->getHydrateCallback($mapperGeneratorMetadata->getTarget());

            if (null !== $extractCallback) {
                $constructStatements[] = new Stmt\Expression(new Expr\Assign(
                    new Expr\ArrayDimFetch(new Expr\PropertyFetch(new Expr\Variable('this'), 'extractCallbacks'), new Scalar\String_($propertyMapping->getProperty())),
                    $extractCallback
                ));
            }

            if (null !== $hydrateCallback) {
                $constructStatements[] = new Stmt\Expression(new Expr\Assign(
                    new Expr\ArrayDimFetch(new Expr\PropertyFetch(new Expr\Variable('this'), 'hydrateCallbacks'), new Scalar\String_($propertyMapping->getProperty())),
                    $hydrateCallback
                ));
            }

            if ($propertyMapping->checkExists()) {
                if (\stdClass::class === $mapperGeneratorMetadata->getSource()) {
                    $conditions[] = new Expr\FuncCall(new Name('property_exists'), [
                        new Arg($sourceInput),
                        new Arg(new Scalar\String_($propertyMapping->getProperty())),
                    ]);
                }

                if ('array' === $mapperGeneratorMetadata->getSource()) {
                    $conditions[] = new Expr\FuncCall(new Name('array_key_exists'), [
                        new Arg(new Scalar\String_($propertyMapping->getProperty())),
                        new Arg($sourceInput),
                    ]);
                }
            }

            $conditions[] = new Expr\MethodCall($contextVariable, 'isAllowedAttribute', [
                new Arg(new Scalar\String_($propertyMapping->getProperty())),
            ]);

            if (null !== $propertyMapping->getSourceGroups()) {
                $conditions[] = new Expr\BinaryOp\BooleanAnd(
                    new Expr\BinaryOp\NotIdentical(
                        new Expr\ConstFetch(new Name('null')),
                        new Expr\MethodCall(new Expr\Variable('context'), 'getGroups')
                    ),
                    new Expr\FuncCall(new Name('array_intersect'), [
                        new Arg(new Expr\MethodCall(new Expr\Variable('context'), 'getGroups')),
                        new Arg(new Expr\Array_(array_map(function (string $group) {
                            return new Expr\ArrayItem(new Scalar\String_($group));
                        }, $propertyMapping->getSourceGroups()))),
                    ])
                );
            }

            if (null !== $propertyMapping->getTargetGroups()) {
                $conditions[] = new Expr\BinaryOp\BooleanAnd(
                    new Expr\BinaryOp\NotIdentical(
                        new Expr\ConstFetch(new Name('null')),
                        new Expr\MethodCall(new Expr\Variable('context'), 'getGroups')
                    ),
                    new Expr\FuncCall(new Name('array_intersect'), [
                        new Arg(new Expr\MethodCall(new Expr\Variable('context'), 'getGroups')),
                        new Arg(new Expr\Array_(array_map(function (string $group) {
                            return new Expr\ArrayItem(new Scalar\String_($group));
                        }, $propertyMapping->getTargetGroups()))),
                    ])
                );
            }

            if (null !== $propertyMapping->getMaxDepth()) {
                $conditions[] = new Expr\BinaryOp\SmallerOrEqual(
                    new Expr\MethodCall($contextVariable, 'getDepth'),
                    new Scalar\LNumber($propertyMapping->getMaxDepth())
                );
            }

            if (\count($conditions) > 0) {
                $condition = array_shift($conditions);

                while (\count($conditions) > 0) {
                    $condition = new Expr\BinaryOp\BooleanAnd($condition, array_shift($conditions));
                }

                $propStatements = [new Stmt\If_($condition, [
                    'stmts' => $propStatements,
                ])];
            }

            $statements = array_merge(
                $statements,
                $propStatements
            );
        }

        $statements[] = new Stmt\Return_($result);

        $mapMethod = new Stmt\ClassMethod('map', [
            'flags' => Stmt\Class_::MODIFIER_PUBLIC,
            'params' => [
                new Param(new Expr\Variable($sourceInput->name)),
                new Param(new Expr\Variable('context'), null, new Name\FullyQualified(Context::class)),
            ],
            'byRef' => true,
            'stmts' => $statements,
        ]);

        $constructMethod = new Stmt\ClassMethod('__construct', [
            'flags' => Stmt\Class_::MODIFIER_PUBLIC,
            'stmts' => $constructStatements,
        ]);

        $classStmts = [$constructMethod, $mapMethod];

        if (\count($injectMapperStatements) > 0) {
            $classStmts[] = new Stmt\ClassMethod('injectMappers', [
                'flags' => Stmt\Class_::MODIFIER_PUBLIC,
                'params' => [
                    new Param(new Expr\Variable('autoMapperRegistry'), null, new Name\FullyQualified(AutoMapperRegistryInterface::class)),
                ],
                'returnType' => 'void',
                'stmts' => $injectMapperStatements,
            ]);
        }

        return new Stmt\Class_(new Name($mapperGeneratorMetadata->getMapperClassName()), [
            'flags' => Stmt\Class_::MODIFIER_FINAL,
            'extends' => new Name\FullyQualified(GeneratedMapper::class),
            'stmts' => $classStmts,
        ]);
    }

    private function getCreateObjectStatements(MapperGeneratorMetadataInterface $mapperMetadata, Expr\Variable $result, Expr\Variable $contextVariable, Expr\Variable $sourceInput, UniqueVariableScope $uniqueVariableScope): array
    {
        if ('array' === $mapperMetadata->getTarget()) {
            return [[new Stmt\Expression(new Expr\Assign($result, new Expr\Array_()))], [], [], []];
        }

        if (\stdClass::class === $mapperMetadata->getTarget()) {
            return [[new Stmt\Expression(new Expr\Assign($result, new Expr\New_(new Name(\stdClass::class))))], [], [], []];
        }

        $reflectionClass = new \ReflectionClass($mapperMetadata->getTarget());
        $targetConstructor = $reflectionClass->getConstructor();
        $createObjectStatements = [];
        $inConstructor = [];
        $constructStatements = [];
        $injectMapperStatements = [];
        /** @var ClassDiscriminatorMapping $classDiscriminatorMapping */
        $classDiscriminatorMapping = 'array' !== $mapperMetadata->getTarget() && null !== $this->classDiscriminator ? $this->classDiscriminator->getMappingForClass($mapperMetadata->getTarget()) : null;

        if (null !== $classDiscriminatorMapping && null !== ($propertyMapping = $mapperMetadata->getPropertyMapping($classDiscriminatorMapping->getTypeProperty()))) {
            [$output, $createObjectStatements] = $propertyMapping->getTransformer()->transform($propertyMapping->getReadAccessor()->getExpression($sourceInput), $uniqueVariableScope, $propertyMapping);

            foreach ($classDiscriminatorMapping->getTypesMapping() as $typeValue => $typeTarget) {
                $mapperName = 'Discriminator_Mapper_'.$mapperMetadata->getSource().'_'.$typeTarget;

                $injectMapperStatements[] = new Stmt\Expression(new Expr\Assign(
                    new Expr\ArrayDimFetch(new Expr\PropertyFetch(new Expr\Variable('this'), 'mappers'), new Scalar\String_($mapperName)),
                    new Expr\MethodCall(new Expr\Variable('autoMapperRegistry'), 'getMapper', [
                        new Arg(new Scalar\String_($mapperMetadata->getSource())),
                        new Arg(new Scalar\String_($typeTarget)),
                    ])
                ));
                $createObjectStatements[] = new Stmt\If_(new Expr\BinaryOp\Identical(
                    new Scalar\String_($typeValue),
                    $output
                ), [
                    'stmts' => [
                        new Stmt\Return_(new Expr\MethodCall(new Expr\ArrayDimFetch(
                            new Expr\PropertyFetch(new Expr\Variable('this'), 'mappers'),
                            new Scalar\String_($mapperName)
                        ), 'map', [
                            new Arg($sourceInput),
                            new Expr\Variable('context'),
                        ])),
                    ],
                ]);
            }
        }

        $propertiesMapping = $mapperMetadata->getPropertiesMapping();

        if (null !== $targetConstructor && $mapperMetadata->hasConstructor()) {
            $constructArguments = [];

            /** @var PropertyMapping $propertyMapping */
            foreach ($propertiesMapping as $propertyMapping) {
                if (null === $propertyMapping->getWriteMutator()->getParameter()) {
                    continue;
                }

                $constructVar = new Expr\Variable($uniqueVariableScope->getUniqueName('constructArg'));

                [$output, $propStatements] = $propertyMapping->getTransformer()->transform($propertyMapping->getReadAccessor()->getExpression($sourceInput), $propertyMapping, $uniqueVariableScope);
                $constructArguments[$propertyMapping->getWriteMutator()->getParameter()->getPosition()] = new Arg($constructVar);

                $propStatements[] = new Stmt\Expression(new Expr\Assign($constructVar, $output));
                $createObjectStatements[] = new Stmt\If_(new Expr\MethodCall($contextVariable, 'hasConstructorArgument', [
                    new Arg(new Scalar\String_($mapperMetadata->getTarget())),
                    new Arg(new Scalar\String_($propertyMapping->getProperty())),
                ]), [
                    'stmts' => [
                        new Stmt\Expression(new Expr\Assign($constructVar, new Expr\MethodCall($contextVariable, 'getConstructorArgument', [
                            new Arg(new Scalar\String_($mapperMetadata->getTarget())),
                            new Arg(new Scalar\String_($propertyMapping->getProperty())),
                        ]))),
                    ],
                    'else' => new Stmt\Else_($propStatements),
                ]);

                $inConstructor[] = $propertyMapping->getProperty();
            }

            foreach ($targetConstructor->getParameters() as $constructorParameter) {
                if (!\array_key_exists($constructorParameter->getPosition(), $constructArguments) && $constructorParameter->isDefaultValueAvailable()) {
                    $constructVar = new Expr\Variable($uniqueVariableScope->getUniqueName('constructArg'));

                    $createObjectStatements[] = new Stmt\If_(new Expr\MethodCall($contextVariable, 'hasConstructorArgument', [
                        new Arg(new Scalar\String_($mapperMetadata->getTarget())),
                        new Arg(new Scalar\String_($constructorParameter->getName())),
                    ]), [
                        'stmts' => [
                            new Stmt\Expression(new Expr\Assign($constructVar, new Expr\MethodCall($contextVariable, 'getConstructorArgument', [
                                new Arg(new Scalar\String_($mapperMetadata->getTarget())),
                                new Arg(new Scalar\String_($constructorParameter->getName())),
                            ]))),
                        ],
                        'else' => new Stmt\Else_([
                            new Stmt\Expression(new Expr\Assign($constructVar, $this->getValueAsExpr($constructorParameter->getDefaultValue()))),
                        ]),
                    ]);

                    $constructArguments[$constructorParameter->getPosition()] = new Arg($constructVar);
                }
            }

            $createObjectStatements[] = new Stmt\Expression(new Expr\Assign($result, new Expr\New_(new Name\FullyQualified($mapperMetadata->getTarget()), $constructArguments)));
        } elseif (null !== $targetConstructor && $mapperMetadata->isTargetCloneable()) {
            $constructStatements[] = new Stmt\Expression(new Expr\Assign(
                new Expr\PropertyFetch(new Expr\Variable('this'), 'cachedTarget'),
                new Expr\MethodCall(new Expr\New_(new Name\FullyQualified(\ReflectionClass::class), [
                    new Arg(new Scalar\String_($mapperMetadata->getTarget())),
                ]), 'newInstanceWithoutConstructor')
            ));
            $createObjectStatements[] = new Stmt\Expression(new Expr\Assign($result, new Expr\Clone_(new Expr\PropertyFetch(new Expr\Variable('this'), 'cachedTarget'))));
        } elseif (null !== $targetConstructor) {
            $constructStatements[] = new Stmt\Expression(new Expr\Assign(
                new Expr\PropertyFetch(new Expr\Variable('this'), 'cachedTarget'),
                new Expr\New_(new Name\FullyQualified(\ReflectionClass::class), [
                    new Arg(new Scalar\String_($mapperMetadata->getTarget())),
                ])
            ));
            $createObjectStatements[] = new Stmt\Expression(new Expr\Assign($result, new Expr\MethodCall(
                new Expr\PropertyFetch(new Expr\Variable('this'), 'cachedTarget'),
                'newInstanceWithoutConstructor'
            )));
        } else {
            $createObjectStatements[] = new Stmt\Expression(new Expr\Assign($result, new Expr\New_(new Name\FullyQualified($mapperMetadata->getTarget()))));
        }

        return [$createObjectStatements, $inConstructor, $constructStatements, $injectMapperStatements];
    }

    private function getValueAsExpr($value)
    {
        $expr = $this->parser->parse('<?php '.var_export($value, true).';')[0];

        if ($expr instanceof Stmt\Expression) {
            return $expr->expr;
        }

        return $expr;
    }
}
