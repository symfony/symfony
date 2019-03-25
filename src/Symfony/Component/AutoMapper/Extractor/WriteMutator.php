<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\AutoMapper\Extractor;

use PhpParser\Node\Arg;
use PhpParser\Node\Expr;
use PhpParser\Node\Name;
use PhpParser\Node\Param;
use PhpParser\Node\Scalar;
use PhpParser\Node\Stmt;
use Symfony\Component\AutoMapper\Exception\CompileException;

/**
 * Writes mutator tell how to write to a property.
 *
 * @author Joel Wurtz <jwurtz@jolicode.com>
 */
final class WriteMutator
{
    public const TYPE_METHOD = 1;
    public const TYPE_PROPERTY = 2;
    public const TYPE_ARRAY_DIMENSION = 3;
    public const TYPE_CONSTRUCTOR = 4;

    private $type;
    private $name;
    private $private;
    private $parameter;

    public function __construct(int $type, string $name, bool $private = false, \ReflectionParameter $parameter = null)
    {
        $this->type = $type;
        $this->name = $name;
        $this->private = $private;
        $this->parameter = $parameter;
    }

    public function getType(): int
    {
        return $this->type;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function isPrivate(): bool
    {
        return $this->private;
    }

    /**
     * Get AST expression for writing from a value to an output.
     *
     * @throws CompileException
     */
    public function getExpression(Expr\Variable $output, Expr $value, bool $byRef = false): ?Expr
    {
        if (self::TYPE_METHOD === $this->type) {
            return new Expr\MethodCall($output, $this->name, [
                new Arg($value),
            ]);
        }

        if (self::TYPE_PROPERTY === $this->type) {
            if ($this->private) {
                return new Expr\FuncCall(
                    new Expr\ArrayDimFetch(new Expr\PropertyFetch(new Expr\Variable('this'), 'hydrateCallbacks'), new Scalar\String_($this->name)),
                    [
                        new Arg($output),
                        new Arg($value),
                    ]
                );
            }
            if ($byRef) {
                return new Expr\AssignRef(new Expr\PropertyFetch($output, $this->name), $value);
            }

            return new Expr\Assign(new Expr\PropertyFetch($output, $this->name), $value);
        }

        if (self::TYPE_ARRAY_DIMENSION === $this->type) {
            if ($byRef) {
                return new Expr\AssignRef(new Expr\ArrayDimFetch($output, new Scalar\String_($this->name)), $value);
            }

            return new Expr\Assign(new Expr\ArrayDimFetch($output, new Scalar\String_($this->name)), $value);
        }

        if (self::TYPE_CONSTRUCTOR === $this->type) {
            return null;
        }

        throw new CompileException('Invalid accessor for write expression');
    }

    /**
     * Get AST expression for binding closure when dealing with private property.
     */
    public function getHydrateCallback($className): ?Expr
    {
        if (self::TYPE_PROPERTY !== $this->type || !$this->private) {
            return null;
        }

        return new Expr\StaticCall(new Name\FullyQualified(\Closure::class), 'bind', [
            new Arg(new Expr\Closure([
                'params' => [
                    new Param(new Expr\Variable('object')),
                    new Param(new Expr\Variable('value')),
                ],
                'stmts' => [
                    new Stmt\Expression(new Expr\Assign(new Expr\PropertyFetch(new Expr\Variable('object'), $this->name), new Expr\Variable('value'))),
                ],
            ])),
            new Arg(new Expr\ConstFetch(new Name('null'))),
            new Arg(new Scalar\String_(new Name\FullyQualified($className))),
        ]);
    }

    /**
     * Get reflection parameter.
     */
    public function getParameter(): ?\ReflectionParameter
    {
        return $this->parameter;
    }
}
