<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Serializer\Builder;

use PhpParser\Builder\Class_;
use PhpParser\Builder\Namespace_;
use PhpParser\BuilderFactory;
use PhpParser\Node;
use PhpParser\ParserFactory;
use PhpParser\PrettyPrinter;
use Symfony\Component\Serializer\Exception\DenormalizingUnionFailedException;
use Symfony\Component\Serializer\Normalizer\DenormalizerAwareInterface;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerAwareInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;

/**
 * The main class to create a new Normalizer from a ClassDefinition.
 *
 * @author Tobias Nyholm <tobias.nyholm@gmail.com>
 *
 * @experimental in 7.1
 */
class NormalizerBuilder
{
    private PrettyPrinter\Standard $printer;
    private BuilderFactory $factory;

    public function __construct()
    {
        if (!class_exists(ParserFactory::class)) {
            throw new \LogicException(sprintf('You cannot use "%s" as the "nikic/php-parser" package is not installed. Try running "composer require nikic/php-parser".', static::class));
        }

        $this->factory = new BuilderFactory();
        $this->printer = new PrettyPrinter\Standard();
    }

    public function build(ClassDefinition $definition, string $outputDir): BuildResult
    {
        $namespace = $this->factory->namespace($definition->getNewNamespace())
            ->addStmt($this->factory->use($definition->getNamespaceAndClass()));

        $class = $this->factory->class($definition->getNewClassName());
        $this->addRequiredMethods($definition, $namespace, $class);
        $this->addNormailizeMethod($definition, $namespace, $class);
        $this->addDenormailizeMethod($definition, $namespace, $class);

        // Add class to namespace
        $namespace->addStmt($class);
        $node = $namespace->getNode();

        // Print
        @mkdir($outputDir, 0777, true);
        $outputFile = $outputDir.'/'.$definition->getNewClassName().'.php';
        file_put_contents($outputFile, $this->printer->prettyPrintFile([$node]));

        return new BuildResult(
            $outputFile,
            $definition->getNewClassName(),
            sprintf('%s\\%s', $definition->getNewNamespace(), $definition->getNewClassName())
        );
    }

    /**
     * Generate a private helper class to normalize subtypes.
     */
    private function generateNormalizeChildMethod(Namespace_ $namespace, Class_ $class): void
    {
        $namespace->addStmt($this->factory->use(NormalizerAwareInterface::class));
        $class->implement('NormalizerAwareInterface');
        $class->addStmt($this->factory->property('normalizer')
            ->makePrivate()
            ->setType('null|NormalizerInterface')
            ->setDefault(null));

        // public function setNormalizer(NormalizerInterface $normalizer): void;
        $class->addStmt($this->factory->method('setNormalizer')
            ->makePublic()
            ->addParam($this->factory->param('normalizer')->setType('NormalizerInterface'))
            ->setReturnType('void')
            ->addStmt(
                new Node\Stmt\Expression(
                    new Node\Expr\Assign(
                        $this->factory->propertyFetch(new Node\Expr\Variable('this'), 'normalizer'),
                        new Node\Expr\Variable('normalizer')
                    )
                )
            )
        );

        // private function normalizeChild(mixed $object, ?string $format, array $context, bool $canBeIterable): mixed;
        $class->addStmt($this->factory->method('normalizeChild')
            ->makePrivate()
            ->addParam($this->factory->param('object')->setType('mixed'))
            ->addParam($this->factory->param('format')->setType('?string'))
            ->addParam($this->factory->param('context')->setType('array'))
            ->addParam($this->factory->param('canBeIterable')->setType('bool'))
            ->setReturnType('mixed')
            ->addStmts([
                new Node\Stmt\If_(
                    new Node\Expr\BinaryOp\BooleanOr(
                        $this->factory->funcCall(new Node\Name('is_scalar'), [
                            new Node\Arg(new Node\Expr\Variable('object')),
                        ]),
                        new Node\Expr\BinaryOp\Identical(
                            new Node\Expr\ConstFetch(new Node\Name('null')),
                            new Node\Expr\Variable('object')
                        )
                    ),
                    [
                        'stmts' => [
                            new Node\Stmt\Return_(new Node\Expr\Variable('object')),
                        ],
                    ]
                ),
                // new line
                new Node\Stmt\If_(
                    new Node\Expr\BinaryOp\BooleanAnd(
                        new Node\Expr\Variable('canBeIterable'),
                        $this->factory->funcCall(new Node\Name('is_iterable'), [
                            new Node\Arg(new Node\Expr\Variable('object')),
                        ])
                    ),
                    [
                        'stmts' => [
                            new Node\Stmt\Return_(
                                new Node\Expr\FuncCall(
                                    new Node\Name('array_map'),
                                    [
                                        new Node\Arg(
                                            new Node\Expr\ArrowFunction([
                                                'params' => [new Node\Param(new Node\Expr\Variable('item'))],
                                                'expr' => $this->factory->methodCall(
                                                    new Node\Expr\Variable('this'),
                                                    'normalizeChild',
                                                    [
                                                        new Node\Arg(new Node\Expr\Variable('item')),
                                                        new Node\Arg(new Node\Expr\Variable('format')),
                                                        new Node\Arg(new Node\Expr\Variable('context')),
                                                        new Node\Arg(new Node\Expr\ConstFetch(new Node\Name('true'))),
                                                    ]
                                                ),
                                            ])
                                        ),
                                        new Node\Arg(new Node\Expr\Variable('object')),
                                    ]
                                )
                            ),
                        ],
                    ]
                ),
                // new line
                new Node\Stmt\Return_(
                    $this->factory->methodCall(
                        $this->factory->propertyFetch(new Node\Expr\Variable('this'), 'normalizer'),
                        'normalize',
                        [
                            new Node\Arg(new Node\Expr\Variable('object')),
                            new Node\Arg(new Node\Expr\Variable('format')),
                            new Node\Arg(new Node\Expr\Variable('context')),
                        ]
                    )
                ),
            ])
        );
    }

    /**
     * Generate a private helper class to de-normalize subtypes.
     */
    private function generateDenormalizeChildMethod(Namespace_ $namespace, Class_ $class): void
    {
        $namespace->addStmt($this->factory->use(DenormalizingUnionFailedException::class));
        $namespace->addStmt($this->factory->use(DenormalizerAwareInterface::class));
        $class->implement('DenormalizerAwareInterface');
        $class->addStmt($this->factory->property('denormalizer')
            ->makePrivate()
            ->setType('null|DenormalizerInterface')
            ->setDefault(null));

        // public function setNormalizer(NormalizerInterface $normalizer): void;
        $class->addStmt($this->factory->method('setDenormalizer')
            ->makePublic()
            ->addParam($this->factory->param('denormalizer')->setType('DenormalizerInterface'))
            ->setReturnType('void')
            ->addStmt(
                new Node\Stmt\Expression(
                    new Node\Expr\Assign(
                        $this->factory->propertyFetch(new Node\Expr\Variable('this'), 'denormalizer'),
                        new Node\Expr\Variable('denormalizer')
                    )
                )
            )
        );

        // private function denormalizeChild(mixed $data, string $type, ?string $format, array $context, bool $canBeIterable): mixed;
        $class->addStmt($this->factory->method('denormalizeChild')
            ->makePrivate()
            ->addParam($this->factory->param('data')->setType('mixed'))
            ->addParam($this->factory->param('type')->setType('string'))
            ->addParam($this->factory->param('format')->setType('?string'))
            ->addParam($this->factory->param('context')->setType('array'))
            ->addParam($this->factory->param('canBeIterable')->setType('bool'))
            ->setReturnType('mixed')
            ->addStmts([
                new Node\Stmt\If_(
                    new Node\Expr\BinaryOp\BooleanOr(
                        $this->factory->funcCall(new Node\Name('is_scalar'), [
                            new Node\Arg(new Node\Expr\Variable('data')),
                        ]),
                        new Node\Expr\BinaryOp\Identical(
                            new Node\Expr\ConstFetch(new Node\Name('null')),
                            new Node\Expr\Variable('data')
                        )
                    ),
                    [
                        'stmts' => [
                            new Node\Stmt\Return_(new Node\Expr\Variable('data')),
                        ],
                    ]
                ),
                // new line
                new Node\Stmt\If_(
                    new Node\Expr\BinaryOp\BooleanAnd(
                        new Node\Expr\Variable('canBeIterable'),
                        $this->factory->funcCall(new Node\Name('is_iterable'), [
                            new Node\Arg(new Node\Expr\Variable('data')),
                        ])
                    ),
                    [
                        'stmts' => [
                            new Node\Stmt\Return_(
                                new Node\Expr\FuncCall(
                                    new Node\Name('array_map'),
                                    [
                                        new Node\Arg(
                                            new Node\Expr\ArrowFunction([
                                                'params' => [new Node\Param(new Node\Expr\Variable('item'))],
                                                'expr' => $this->factory->methodCall(
                                                    new Node\Expr\Variable('this'),
                                                    'denormalizeChild',
                                                    [
                                                        new Node\Arg(new Node\Expr\Variable('item')),
                                                        new Node\Arg(new Node\Expr\Variable('type')),
                                                        new Node\Arg(new Node\Expr\Variable('format')),
                                                        new Node\Arg(new Node\Expr\Variable('context')),
                                                        new Node\Arg(new Node\Expr\ConstFetch(new Node\Name('true'))),
                                                    ]
                                                ),
                                            ])
                                        ),
                                        new Node\Arg(new Node\Expr\Variable('data')),
                                    ]
                                )
                            ),
                        ],
                    ]
                ),
                // new line
                new Node\Stmt\Return_(
                    $this->factory->methodCall(
                        $this->factory->propertyFetch(new Node\Expr\Variable('this'), 'denormalizer'),
                        'denormalize',
                        [
                            new Node\Arg(new Node\Expr\Variable('data')),
                            new Node\Arg(new Node\Expr\Variable('type')),
                            new Node\Arg(new Node\Expr\Variable('format')),
                            new Node\Arg(new Node\Expr\Variable('context')),
                        ]
                    )
                ),
            ])
        );
    }

    /**
     * Add methods required by NormalizerInterface and DenormalizerInterface.
     */
    private function addRequiredMethods(ClassDefinition $definition, Namespace_ $namespace, Class_ $class): void
    {
        $namespace
            ->addStmt($this->factory->use(NormalizerInterface::class))
            ->addStmt($this->factory->use(DenormalizerInterface::class));

        $class->implement('NormalizerInterface', 'DenormalizerInterface');

        // public function getSupportedTypes(?string $format): array;
        $class->addStmt($this->factory->method('getSupportedTypes')
            ->makePublic()
            ->addParam($this->factory->param('format')->setType('?string'))
            ->setReturnType('array')
            ->addStmt(new Node\Stmt\Return_(new Node\Expr\Array_([
                new Node\Expr\ArrayItem(
                    new Node\Expr\ConstFetch(new Node\Name('true')),
                    new Node\Expr\ClassConstFetch(new Node\Name($definition->getSourceClassName()), 'class')
                ),
            ])))
        );

        // public function supportsNormalization(mixed $data, string $format = null, array $context = []): bool;
        $class->addStmt($this->factory->method('supportsNormalization')
            ->makePublic()
            ->addParam($this->factory->param('data')->setType('mixed'))
            ->addParam($this->factory->param('format')->setType('string')->setDefault(null))
            ->addParam($this->factory->param('context')->setType('array')->setDefault([]))
            ->setReturnType('bool')
            ->addStmt(new Node\Stmt\Return_(new Node\Expr\Instanceof_(
                new Node\Expr\Variable('data'),
                new Node\Name($definition->getSourceClassName())
            )))
        );

        // public function supportsDenormalization(mixed $data, string $type, string $format = null, array $context = []): bool;
        $class->addStmt($this->factory->method('supportsDenormalization')
            ->makePublic()
            ->addParam($this->factory->param('data')->setType('mixed'))
            ->addParam($this->factory->param('type')->setType('string'))
            ->addParam($this->factory->param('format')->setType('string')->setDefault(null))
            ->addParam($this->factory->param('context')->setType('array')->setDefault([]))
            ->setReturnType('bool')
            ->addStmt(new Node\Stmt\Return_(new Node\Expr\BinaryOp\Identical(
                new Node\Expr\Variable('type'),
                new Node\Expr\ClassConstFetch(new Node\Name($definition->getSourceClassName()), 'class')
            )))
        );
    }

    private function addDenormailizeMethod(ClassDefinition $definition, Namespace_ $namespace, Class_ $class): void
    {
        $needsChildDenormalizer = false;
        $body = [
            new Node\Stmt\Expression(new Node\Expr\Assign(
                new Node\Expr\Variable('data'),
                new Node\Expr\Cast\Array_(new Node\Expr\Variable('data'))
            )),
        ];

        if (ClassDefinition::CONSTRUCTOR_NONE === $definition->getConstructorType()) {
            $body[] = new Node\Stmt\Expression(new Node\Expr\Assign(
                new Node\Expr\Variable('output'),
                new Node\Expr\New_(
                    new Node\Name($definition->getSourceClassName())
                )
            ));
        } elseif (ClassDefinition::CONSTRUCTOR_PUBLIC !== $definition->getConstructorType()) {
            $body[] = new Node\Stmt\Expression(new Node\Expr\Assign(
                new Node\Expr\Variable('output'),
                $this->factory->methodCall(
                    new Node\Expr\New_(
                        new Node\Name\FullyQualified('ReflectionClass'),
                        [new Node\Arg(new Node\Expr\ClassConstFetch(new Node\Name($definition->getSourceClassName()), 'class'))]
                    ),
                    'newInstanceWithoutConstructor'
                )));
        } else {
            $constructorArguments = [];

            foreach ($definition->getConstructorArguments() as $i => $propertyDefinition) {
                $variable = new Node\Expr\ArrayDimFetch(new Node\Expr\Variable('data'), new Node\Scalar\String_($propertyDefinition->getNormalizedName()));
                $targetClasses = $propertyDefinition->getNonPrimitiveTypes();
                $canBeIterable = $propertyDefinition->isCollection();

                $defaultValue = $propertyDefinition->getConstructorDefaultValue();
                if (\is_object($defaultValue)) {
                    // public function __construct($foo = new \stdClass());
                    // There is no support for parameters to the object.
                    $defaultValue = new Node\Expr\New_(new Node\Name\FullyQualified($defaultValue::class));
                } else {
                    $defaultValue = $this->factory->val($defaultValue);
                }

                if ([] === $targetClasses && $propertyDefinition->hasConstructorDefaultValue()) {
                    $constructorArguments[] = new Node\Arg(new Node\Expr\BinaryOp\Coalesce(
                        $variable,
                        $defaultValue
                    ));
                    continue;
                } elseif ([] === $targetClasses) {
                    $constructorArguments[] = new Node\Arg($variable);
                    continue;
                }

                $needsChildDenormalizer = true;
                $tempVariableName = 'argument'.$i;

                if (\count($targetClasses) > 1) {
                    $variableOutput = $this->generateCodeToDeserializeMultiplePossibleClasses($targetClasses, $canBeIterable, $tempVariableName, $variable, $propertyDefinition->getNormalizedName(), $definition->getNamespaceAndClass());
                } else {
                    $variableOutput = [
                        new Node\Stmt\Expression(
                            new Node\Expr\Assign(
                                new Node\Expr\Variable($tempVariableName),
                                $this->factory->methodCall(
                                    new Node\Expr\Variable('this'),
                                    'denormalizeChild',
                                    [
                                        new Node\Arg($variable),
                                        new Node\Arg(new Node\Expr\ClassConstFetch(new Node\Name\FullyQualified($targetClasses[0]), 'class')),
                                        new Node\Arg(new Node\Expr\Variable('format')),
                                        new Node\Arg(new Node\Expr\Variable('context')),
                                        new Node\Arg(new Node\Expr\ConstFetch(new Node\Name($canBeIterable ? 'true' : 'false'))),
                                    ]
                                )
                            )
                        ),
                    ];
                }

                if ($propertyDefinition->hasConstructorDefaultValue()) {
                    $variableOutput = [new Node\Stmt\If_(new Node\Expr\BooleanNot(
                        $this->factory->funcCall('array_key_exists', [
                            new Node\Arg(new Node\Scalar\String_($propertyDefinition->getNormalizedName())),
                            new Node\Arg(new Node\Expr\Variable('data')),
                        ])
                    ), [
                        'stmts' => [
                            new Node\Stmt\Expression(new Node\Expr\Assign(
                                new Node\Expr\Variable($tempVariableName),
                                $defaultValue
                            )),
                        ],
                        'else' => new Node\Stmt\Else_($variableOutput),
                        ]
                    )];
                }

                // Add $variableOutput to the end of $body
                $body = array_merge($body, $variableOutput);

                $constructorArguments[] = new Node\Arg(new Node\Expr\Variable($tempVariableName));
            }

            $body[] = new Node\Stmt\Expression(new Node\Expr\Assign(
                new Node\Expr\Variable('output'),
                new Node\Expr\New_(
                    new Node\Name($definition->getSourceClassName()),
                    $constructorArguments
                ),
            ));
        }

        // Start working with non-constructor properties
        $i = 0;
        foreach ($definition->getDefinitions() as $propertyDefinition) {
            if (!$propertyDefinition->isWriteable() || $propertyDefinition->isConstructorArgument()) {
                continue;
            }

            $tempVariableName = null;
            $variableOutput = [];

            $variable = new Node\Expr\ArrayDimFetch(new Node\Expr\Variable('data'), new Node\Scalar\String_($propertyDefinition->getNormalizedName()));
            $targetClasses = $propertyDefinition->getNonPrimitiveTypes();

            if ([] !== $targetClasses) {
                $needsChildDenormalizer = true;
                $tempVariableName = 'setter'.$i++;

                if (\count($targetClasses) > 1) {
                    $variableOutput = $this->generateCodeToDeserializeMultiplePossibleClasses($targetClasses, $propertyDefinition->isCollection(), $tempVariableName, $variable, $propertyDefinition->getNormalizedName(), $definition->getNamespaceAndClass());
                } else {
                    $variableOutput = [
                        new Node\Stmt\Expression(
                            new Node\Expr\Assign(
                                new Node\Expr\Variable($tempVariableName),
                                $this->factory->methodCall(
                                    new Node\Expr\Variable('this'),
                                    'denormalizeChild',
                                    [
                                        new Node\Arg($variable),
                                        new Node\Arg(new Node\Expr\ClassConstFetch(new Node\Name\FullyQualified($targetClasses[0]), 'class')),
                                        new Node\Arg(new Node\Expr\Variable('format')),
                                        new Node\Arg(new Node\Expr\Variable('context')),
                                        new Node\Arg(new Node\Expr\ConstFetch(new Node\Name($propertyDefinition->isCollection() ? 'true' : 'false'))),
                                    ]
                                )
                            )
                        ),
                    ];
                }
            }

            $result = null === $tempVariableName ? $variable : new Node\Expr\Variable($tempVariableName);
            if (null !== $method = $propertyDefinition->getSetterName()) {
                $variableOutput[] = new Node\Stmt\Expression(new Node\Expr\MethodCall(
                    new Node\Expr\Variable('output'),
                    $method,
                    [new Node\Arg($result)]
                ));
            } else {
                $variableOutput[] = new Node\Stmt\Expression(new Node\Expr\Assign(
                    new Node\Expr\PropertyFetch(new Node\Expr\Variable('output'), $propertyDefinition->getPropertyName()),
                    $result
                ));
            }

            $body[] = new Node\Stmt\If_(
                $this->factory->funcCall('array_key_exists', [
                    new Node\Arg(new Node\Scalar\String_($propertyDefinition->getNormalizedName())),
                    new Node\Arg(new Node\Expr\Variable('data')),
                ]),
                ['stmts' => $variableOutput]
            );
        }

        $class->addStmt($this->factory->method('denormalize')
            ->makePublic()
            ->addParam($this->factory->param('data')->setType('mixed'))
            ->addParam($this->factory->param('type')->setType('string'))
            ->addParam($this->factory->param('format')->setType('?string')->setDefault(null))
            ->addParam($this->factory->param('context')->setType('array')->setDefault([]))
            ->setReturnType('mixed')
            ->addStmts($body)
            ->addStmt(new Node\Stmt\Return_(new Node\Expr\Variable('output')))
        );

        if ($needsChildDenormalizer) {
            $this->generateDenormalizeChildMethod($namespace, $class);
        }
    }

    private function addNormailizeMethod(ClassDefinition $definition, Namespace_ $namespace, Class_ $class): void
    {
        $bodyArrayItems = [];
        $needsChildNormalizer = false;
        foreach ($definition->getDefinitions() as $propertyDefinition) {
            if (!$propertyDefinition->isReadable()) {
                continue;
            }

            if (null !== $method = $propertyDefinition->getGetterName()) {
                // $object->$method()
                $accessor = $this->factory->methodCall(new Node\Expr\Variable('object'), $method);
            } else {
                // $object->property
                $accessor = $this->factory->propertyFetch(new Node\Expr\Variable('object'), $propertyDefinition->getPropertyName());
            }

            if ($propertyDefinition->hasNoTypeDefinition() || [] !== $propertyDefinition->getNonPrimitiveTypes()) {
                $needsChildNormalizer = true;
                // $this->normalizeChild($accessor, $format, $context, bool);
                $accessor = $this->factory->methodCall(new Node\Expr\Variable('this'), 'normalizeChild', [
                    $accessor,
                    new Node\Arg(new Node\Expr\Variable('format')),
                    new Node\Arg(new Node\Expr\Variable('context')),
                    new Node\Arg(new Node\Expr\ConstFetch(new Node\Name($propertyDefinition->isCollection() || $propertyDefinition->hasNoTypeDefinition() ? 'true' : 'false'))),
                ]);
            }

            $bodyArrayItems[] = new Node\Expr\ArrayItem($accessor, new Node\Scalar\String_($propertyDefinition->getNormalizedName()));
        }

        // public function normalize(mixed $object, string $format = null, array $context = []): array|string|int|float|bool|\ArrayObject|null;
        $class->addStmt($this->factory->method('normalize')
            ->makePublic()
            ->addParam($this->factory->param('object')->setType('mixed'))
            ->addParam($this->factory->param('format')->setType('string')->setDefault(null))
            ->addParam($this->factory->param('context')->setType('array')->setDefault([]))
            ->setReturnType('array|string|int|float|bool|\ArrayObject|null')
            ->setDocComment(sprintf('/**'.\PHP_EOL.'* @param %s $object'.\PHP_EOL.'*/', $definition->getSourceClassName()))
            ->addStmt(new Node\Stmt\Return_(new Node\Expr\Array_($bodyArrayItems))));

        if ($needsChildNormalizer) {
            $this->generateNormalizeChildMethod($namespace, $class);
        }
    }

    /**
     * When the type-hint has many different classes, then we need to try to denormalize them
     * one by one. We are happy when we dont get any exceptions thrown.
     *
     * @return Node\Stmt[]
     */
    private function generateCodeToDeserializeMultiplePossibleClasses(array $targetClasses, bool $canBeIterable, string $tempVariableName, Node\Expr $variable, string $keyName, string $classNs): array
    {
        $arrayItems = [];
        foreach ($targetClasses as $class) {
            $arrayItems[] = new Node\Expr\ArrayItem(new Node\Expr\ClassConstFetch(new Node\Name\FullyQualified($class), 'class'));
        }

        return [
            new Node\Stmt\Expression(
                new Node\Expr\Assign(
                    new Node\Expr\Variable('exceptions'),
                    new Node\Expr\Array_()
                )
            ),
            new Node\Stmt\Expression(
                new Node\Expr\Assign(
                    new Node\Expr\Variable($tempVariableName.'HasValue'),
                    new Node\Expr\ConstFetch(new Node\Name('false'))
                )
            ),
            new Node\Stmt\Foreach_(
                new Node\Expr\Array_($arrayItems),
                new Node\Expr\Variable('class'),
                [
                    'stmts' => [
                        new Node\Stmt\TryCatch(
                            // statements
                            [
                                new Node\Stmt\Expression(
                                    new Node\Expr\Assign(
                                        new Node\Expr\Variable($tempVariableName),
                                        $this->factory->methodCall(
                                            new Node\Expr\Variable('this'),
                                            'denormalizeChild',
                                            [
                                                new Node\Arg($variable),
                                                new Node\Arg(new Node\Expr\Variable('class')),
                                                new Node\Arg(new Node\Expr\Variable('format')),
                                                new Node\Arg(new Node\Expr\Variable('context')),
                                                new Node\Arg(new Node\Expr\ConstFetch(new Node\Name($canBeIterable ? 'true' : 'false'))),
                                            ]
                                        )
                                    )
                                ),
                                new Node\Stmt\Expression(
                                    new Node\Expr\Assign(
                                        new Node\Expr\Variable($tempVariableName.'HasValue'),
                                        new Node\Expr\ConstFetch(new Node\Name('true'))
                                    )
                                ),
                                new Node\Stmt\Break_(),
                            ],
                            // Catches
                            [
                                new Node\Stmt\Catch_(
                                    [new Node\Name\FullyQualified(\Throwable::class)],
                                    new Node\Expr\Variable('e'),
                                    [
                                        new Node\Stmt\Expression(
                                            new Node\Expr\Assign(
                                                new Node\Expr\ArrayDimFetch(new Node\Expr\Variable('exceptions')),
                                                new Node\Expr\Variable('e')
                                            )
                                        ),
                                    ]
                                ),
                            ],
                        ),
                    ],
                ]
            ), // end foreach
            new Node\Stmt\If_(
                new Node\Expr\BooleanNot(new Node\Expr\Variable($tempVariableName.'HasValue')),
                [
                    'stmts' => [
                        new Node\Stmt\Expression(
                            new Node\Expr\Throw_(
                                new Node\Expr\New_(
                                    new Node\Name('DenormalizingUnionFailedException'),
                                    [
                                        new Node\Arg(new Node\Scalar\String_('Failed to denormalize key "'.$keyName.'" of class "'.$classNs.'".')),
                                        new Node\Arg(new Node\Expr\Variable('exceptions')),
                                    ]
                                )
                            ),
                        ),
                    ],
                ]
            ),
        ];
    }
}
