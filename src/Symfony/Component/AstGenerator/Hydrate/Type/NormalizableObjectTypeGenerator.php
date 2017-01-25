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
    public function generate($object, array $context = array())
    {
        if (!isset($context['input']) || !($context['input'] instanceof Expr)) {
            throw new MissingContextException('Input variable not defined or not an Expr in generation context');
        }

        if (!isset($context['output']) || !($context['output'] instanceof Expr)) {
            throw new MissingContextException('Output variable not defined or not an Expr in generation context');
        }

        if (!isset($context['normalizer']) || !($context['normalizer'] instanceof Expr)) {
            throw new MissingContextException('Normalizer variable not defined or not an Expr in generation context');
        }

        $normalizationArgs = array(new Arg($context['input']));
        if (isset($context['format'])) {
            $normalizationArgs[] = new Arg($context['format']);
        } else {
            $normalizationArgs[] = new Arg(new Expr\ConstFetch(new Name('null')));
        }
        if (isset($context['context'])) {
            $normalizationArgs[] = new Arg($context['context']);
        }

        $assign = array(
            new Expr\Assign($context['output'], new Expr\MethodCall(
                $context['normalizer'],
                'normalize',
                $normalizationArgs
            ))
        );

        if (isset($context['condition']) && $context['condition']) {
            return array(new Stmt\If_(
                new Expr\BinaryOp\LogicalAnd(
                    new Expr\Instanceof_(new Expr\Variable('data'), new Name($object->getClassName())),
                    new Expr\MethodCall(
                        $context['normalizer'],
                        'supportsNormalization',
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
