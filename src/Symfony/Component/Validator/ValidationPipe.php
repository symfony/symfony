<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Validator;

use Symfony\Component\Validator\Constraints\Sequentially;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/** @template T */
final class ValidationPipe
{
    /** @internal */
    public function __construct(
        /** @var T */
        private readonly mixed $value,
        /** @var non-empty-list<Constraint> */
        private readonly array $constraints,
    ) {
    }

    /**
     * @internal
     *
     * @return self<T>
     */
    public function pipe(Constraint $constraint): self
    {
        return new self($this->value, [...$this->constraints, $constraint]);
    }

    /** @return T */
    public function validOrFail(?ValidatorInterface $validator = null): mixed
    {
        return Validation::createCallable($validator, new Sequentially($this->constraints))($this->value);
    }
}
