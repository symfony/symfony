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
 * Read accessor tell how to read from a property.
 *
 * @author Joel Wurtz <jwurtz@jolicode.com>
 */
final class ReadAccessor
{
    public const TYPE_METHOD = 1;
    public const TYPE_PROPERTY = 2;
    public const TYPE_ARRAY_DIMENSION = 3;
    public const TYPE_SOURCE = 4;

    private $type;

    private $name;

    private $private;

    public function __construct(int $type, string $name, $private = false)
    {
        $this->type = $type;
        $this->name = $name;
        $this->private = $private;
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
     * Get AST expression for reading property from an input.
     *
     * @throws CompileException
     */
    public function getExpression(Expr\Variable $input): Expr
    {
        if (self::TYPE_METHOD === $this->type) {
            return new Expr\MethodCall($input, $this->name);
        }

        if (self::TYPE_PROPERTY === $this->type) {
            if ($this->private) {
                return new Expr\FuncCall(
                    new Expr\ArrayDimFetch(new Expr\PropertyFetch(new Expr\Variable('this'), 'extractCallbacks'), new Scalar\String_($this->name)),
                    [
                        new Arg($input),
                    ]
                );
            }

            return new Expr\PropertyFetch($input, $this->name);
        }

        if (self::TYPE_ARRAY_DIMENSION === $this->type) {
            return new Expr\ArrayDimFetch($input, new Scalar\String_($this->name));
        }

        if (self::TYPE_SOURCE === $this->type) {
            return $input;
        }

        throw new CompileException('Invalid accessor for read expression');
    }

    /**
     * Get AST expression for binding closure when dealing with a private property.
     */
    public function getExtractCallback($className): ?Expr
    {
        if (self::TYPE_PROPERTY !== $this->type || !$this->private) {
            return null;
        }

        return new Expr\StaticCall(new Name\FullyQualified(\Closure::class), 'bind', [
            new Arg(new Expr\Closure([
                'params' => [
                    new Param(new Expr\Variable('object')),
                ],
                'stmts' => [
                    new Stmt\Return_(new Expr\PropertyFetch(new Expr\Variable('object'), $this->name)),
                ],
            ])),
            new Arg(new Expr\ConstFetch(new Name('null'))),
            new Arg(new Scalar\String_(new Name\FullyQualified($className))),
        ]);
    }
}
