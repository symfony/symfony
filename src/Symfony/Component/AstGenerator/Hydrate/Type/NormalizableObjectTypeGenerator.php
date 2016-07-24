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
 * Generate hydration of normalizable object type.
 *
 * @author Guilhem N. <egetick@gmail.com>
 */
class NormalizableObjectTypeGenerator implements AstGeneratorInterface
{
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

        if (!isset($context['normalizer']) || !($context['normalizer'] instanceof Expr)) {
            throw new MissingContextException('Denormalizer variable not defined or not an Expr in generation context');
        }

        $assign = [
            new Expr\Assign($context['output'], $context['input'])
        ];

        if (isset($context['condition']) && $context['condition']) {
            return [new Stmt\If_(
                new Expr\BinaryOp\LogicalAnd(
                    new Expr\FuncCall(
                        'is_object',
                        array(
                            new Arg($context['input']),
                        )
                    ),
                    new Expr\MethodCall(
                        $context['normalizer'],
                        'supportsNormalization'
                        call_user_func(function() use ($context) {
                            $args = array(new Arg($context['input']));
                            if (isset($context['format'])) {
                                $args[] = new Arg($context['input']);
                            } else {
                                $args[] = new Arg(new Expr\ConstFetch(new Name('null')));
                            }
                            if (isset($context['context'])) {
                                $args[] = new Arg($context['context']);
                            }

                            return $args;
                        })
                    )
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
        return $object instanceof Type && Type::BUILTIN_TYPE_OBJECT === $object->getBuiltinType();
    }
}
