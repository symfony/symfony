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
use PhpParser\Node\Scalar;
use Symfony\Component\AstGenerator\AstGeneratorInterface;
use Symfony\Component\AstGenerator\Exception\MissingContextException;
use Symfony\Component\PropertyInfo\Type;

/**
 * Generate hydration of normalizable object type.
 *
 * @author Guilhem N. <egetick@gmail.com>
 */
class DenormalizableObjectTypeGenerator implements AstGeneratorInterface
{
    /**
     * {@inheritdoc}
     *
     * @param Type $object A type extracted with PropertyInfo component
     */
    public function generate($object, array $context = array())
    {
        if (!isset($context['input']) || !($context['input'] instanceof Expr)) {
            throw new MissingContextException('Input variable not defined or not an Expr in generation context');
        }

        if (!isset($context['output']) || !($context['output'] instanceof Expr)) {
            throw new MissingContextException('Output variable not defined or not an Expr in generation context');
        }

        if (!isset($context['denormalizer']) || !($context['denormalizer'] instanceof Expr)) {
            throw new MissingContextException('Denormalizer variable not defined or not an Expr in generation context');
        }

        $denormalizationArgs = array(
            new Arg($context['input']),
            new Arg(new Scalar\String_($object->getClassName())),
        );
        if (isset($context['format'])) {
            $denormalizationArgs[] = new Arg($context['format']);
        } else {
            $denormalizationArgs[] = new Arg(new Expr\ConstFetch(new Name('null')));
        }
        if (isset($context['context'])) {
            $denormalizationArgs[] = new Arg($context['context']);
        }

        $assign = [
            new Expr\Assign($context['output'], new Expr\MethodCall(
                $context['denormalizer'],
                'denormalize',
                $denormalizationArgs
            ))
        ];

        if (isset($context['condition']) && $context['condition']) {
            return array(new Stmt\If_(
                new Expr\BinaryOp\LogicalAnd(
                    new Expr\MethodCall(
                        $context['denormalizer'],
                        'supportsDenormalization',
                        $normalizationArgs
                    )
                ),
                array(
                    'stmts' => $assign
                )
            ));
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
