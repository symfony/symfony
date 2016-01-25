<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\AstGenerator\Hydrate\Type;

use PhpParser\Node\Arg;
use PhpParser\Node\Expr;
use PhpParser\Node\Stmt;
use PhpParser\Node\Name;
use Symfony\Component\AstGenerator\AstGeneratorInterface;
use Symfony\Component\AstGenerator\Exception\MissingContextException;
use Symfony\Component\PropertyInfo\Type;

/**
 * Generate hydration of simple type.
 *
 * @author Joel Wurtz <joel.wurtz@gmail.com>
 */
class TypeGenerator implements AstGeneratorInterface
{
    protected $supportedTypes = [
        Type::BUILTIN_TYPE_BOOL,
        Type::BUILTIN_TYPE_FLOAT,
        Type::BUILTIN_TYPE_INT,
        Type::BUILTIN_TYPE_NULL,
        Type::BUILTIN_TYPE_STRING,
    ];

    protected $conditionMapping = [
        Type::BUILTIN_TYPE_BOOL => 'is_bool',
        Type::BUILTIN_TYPE_FLOAT => 'is_float',
        Type::BUILTIN_TYPE_INT => 'is_int',
        Type::BUILTIN_TYPE_NULL => 'is_null',
        Type::BUILTIN_TYPE_STRING => 'is_string',
    ];

    /**
     * {@inheritdoc}
     *
     * @param Type $object A type extracted with PropertyInfo component
     */
    public function generate($object, array $context = [])
    {
        if (!isset($context['input']) || !($context['input'] instanceof Expr)) {
            throw new MissingContextException('Input variable not defined or not an Expr in generation context');
        }

        if (!isset($context['output']) || !($context['output'] instanceof Expr)) {
            throw new MissingContextException('Output variable not defined or not an Expr in generation context');
        }

        $assign = [
            new Expr\Assign($context['output'], $context['input'])
        ];

        if (isset($context['condition']) && $context['condition']) {
            return [new Stmt\If_(
                new Expr\FuncCall(
                    new Name($this->conditionMapping[$object->getBuiltinType()]),
                    [
                        new Arg($context['input'])
                    ]
                ),
                [
                    'stmts' => $assign
                ]
            )];
        }

        return $assign;
    }

    /**
     * {@inheritdoc}
     */
    public function supportsGeneration($object)
    {
        return $object instanceof Type && in_array($object->getBuiltinType(), $this->supportedTypes);
    }
}
