<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\JsonStreamer\DataModel;

use PhpParser\BuilderFactory;
use PhpParser\Node\Expr;

/**
 * Defines the way to access data using a function (or a method).
 *
 * @author Mathias Arlaud <mathias.arlaud@gmail.com>
 *
 * @internal
 */
final class FunctionDataAccessor implements DataAccessorInterface
{
    /**
     * @param list<DataAccessorInterface> $arguments
     */
    public function __construct(
        private string $functionName,
        private array $arguments,
        private ?DataAccessorInterface $objectAccessor = null,
    ) {
    }

    public function getObjectAccessor(): ?DataAccessorInterface
    {
        return $this->objectAccessor;
    }

    public function withObjectAccessor(?DataAccessorInterface $accessor): self
    {
        return new self($this->functionName, $this->arguments, $accessor);
    }

    public function toPhpExpr(): Expr
    {
        $builder = new BuilderFactory();
        $arguments = array_map(static fn (DataAccessorInterface $argument): Expr => $argument->toPhpExpr(), $this->arguments);

        if (null === $this->objectAccessor) {
            return $builder->funcCall($this->functionName, $arguments);
        }

        return $builder->methodCall($this->objectAccessor->toPhpExpr(), $this->functionName, $arguments);
    }
}
