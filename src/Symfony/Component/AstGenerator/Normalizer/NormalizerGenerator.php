<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\AstGenerator\Normalizer;

use PhpParser\Node\Name;
use PhpParser\Node\Param;
use PhpParser\Node\Stmt;
use PhpParser\Node\Expr;
use PhpParser\Node\Scalar;
use Symfony\Component\AstGenerator\AstGeneratorInterface;
use Symfony\Component\AstGenerator\UniqueVariableScope;

/**
 * Generate a Normalizer given a Class
 *
 * @author Joel Wurtz <joel.wurtz@gmail.com>
 */
class NormalizerGenerator implements AstGeneratorInterface
{
    /** @var AstGeneratorInterface Generator which generate the statements for normalization of a given class */
    protected $normalizeStatementsGenerator;

    /** @var AstGeneratorInterface Generator which generate the statements for denormalization of a given class */
    protected $denormalizeStatementsGenerator;

    /**
     * NormalizerGenerator constructor.
     *
     * @param AstGeneratorInterface $normalizeStatementsGenerator   Generator which generate the statements for normalization of a given class
     * @param AstGeneratorInterface $denormalizeStatementsGenerator Generator which generate the statements for denormalization of a given class
     */
    public function __construct(AstGeneratorInterface $normalizeStatementsGenerator, AstGeneratorInterface $denormalizeStatementsGenerator)
    {
        $this->normalizeStatementsGenerator = $normalizeStatementsGenerator;
        $this->denormalizeStatementsGenerator = $denormalizeStatementsGenerator;
    }

    /**
     * {@inheritdoc}
     */
    public function generate($object, array $context = [])
    {
        if (!isset($context['name'])) {
            $reflectionClass = new \ReflectionClass($object);
            $context['name'] = $reflectionClass->getShortName().'Normalizer';
        }

        return [new Stmt\Class_(
            new Name($context['name']),
            [
                'stmts' => [
                    $this->createSupportsNormalizationMethod($object),
                    $this->createSupportsDenormalizationMethod($object),
                    $this->createNormalizeMethod($object, array_merge($context, [
                        'unique_variable_scope' => new UniqueVariableScope()
                    ])),
                    $this->createDenormalizeMethod($object, array_merge($context, [
                        'unique_variable_scope' => new UniqueVariableScope()
                    ])),
                ],
                'implements' => [
                    new Name('\Symfony\Component\Serializer\Normalizer\DenormalizerInterface'),
                    new Name('\Symfony\Component\Serializer\Normalizer\NormalizerInterface')
                ],
                'extends' => new Name('\Symfony\Component\Serializer\Normalizer\SerializerAwareNormalizer')
            ]
        )];
    }

    /**
     * Create method to check if normalization is supported
     *
     * @param string $class Fully Qualified name of the model class
     *
     * @return Stmt\ClassMethod
     */
    protected function createSupportsNormalizationMethod($class)
    {
        if (strpos($class, '\\') !== 0) {
            $class = '\\'.$class;
        }

        return new Stmt\ClassMethod('supportsNormalization', [
            'type' => Stmt\Class_::MODIFIER_PUBLIC,
            'params' => [
                new Param('data'),
                new Param('format', new Expr\ConstFetch(new Name("null"))),
            ],
            'stmts' => [
                new Stmt\If_(
                    new Expr\Instanceof_(new Expr\Variable('data'), new Name($class)),
                    [
                        'stmts' => [
                            new Stmt\Return_(new Expr\ConstFetch(new Name("true")))
                        ]
                    ]
                ),
                new Stmt\Return_(new Expr\ConstFetch(new Name("false")))
            ]
        ]);
    }

    /**
     * Create method to check if denormalization is supported
     *
     * @param string $class Fully Qualified name of the model class
     *
     * @return Stmt\ClassMethod
     */
    protected function createSupportsDenormalizationMethod($class)
    {
        return new Stmt\ClassMethod('supportsDenormalization', [
            'type' => Stmt\Class_::MODIFIER_PUBLIC,
            'params' => [
                new Param('data'),
                new Param('type'),
                new Param('format', new Expr\ConstFetch(new Name("null"))),
            ],
            'stmts' => [
                new Stmt\If_(
                    new Expr\BinaryOp\NotIdentical(new Expr\Variable('type'), new Scalar\String_($class)),
                    [
                        'stmts' => [
                            new Stmt\Return_(new Expr\ConstFetch(new Name("false")))
                        ]
                    ]
                ),
                new Stmt\Return_(new Expr\ConstFetch(new Name("true")))
            ]
        ]);
    }

    /**
     * Create the normalization method
     *
     * @param string $class   Class to create normalization from
     * @param array  $context Context of generation
     *
     * @return Stmt\ClassMethod
     */
    protected function createNormalizeMethod($class, array $context = [])
    {
        return new Stmt\ClassMethod('normalize', [
            'type' => Stmt\Class_::MODIFIER_PUBLIC,
            'params' => [
                new Param('object'),
                new Param('format', new Expr\ConstFetch(new Name("null"))),
                new Param('context', new Expr\Array_(), 'array'),
            ],
            'stmts' => $this->normalizeStatementsGenerator->generate($class, array_merge($context, [
                'input' => new Expr\Variable('object')
            ]))
        ]);
    }

    /**
     * Create the denormalization method
     *
     * @param string $class   Class to create denormalization from
     * @param array  $context Context of generation
     *
     * @return Stmt\ClassMethod
     */
    protected function createDenormalizeMethod($class, array $context = [])
    {
        return new Stmt\ClassMethod('denormalize', [
            'type' => Stmt\Class_::MODIFIER_PUBLIC,
            'params' => [
                new Param('data'),
                new Param('class'),
                new Param('format', new Expr\ConstFetch(new Name("null"))),
                new Param('context', new Expr\Array_(), 'array'),
            ],
            'stmts' => $this->denormalizeStatementsGenerator->generate($class, array_merge($context, [
                'input' => new Expr\Variable('data')
            ]))
        ]);
    }

    /**
     * {@inheritdoc}
     */
    public function supportsGeneration($object)
    {
        return (is_string($object) && class_exists($object));
    }
}
