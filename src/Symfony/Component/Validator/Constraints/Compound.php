<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Validator\Constraints;

use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\Exception\InvalidArgumentException;

/**
 * Extend this class to create a reusable set of constraints.
 *
 * @author Maxime Steinhausser <maxime.steinhausser@gmail.com>
 */
abstract class Compound extends Composite
{
    /** @var Constraint[] */
    public array $constraints = [];

    public function __construct(mixed $options = null, ?array $groups = null, mixed $payload = null)
    {
        if (null !== $options) {
            throw new InvalidArgumentException(\sprintf('Passing an array of options to configure the "%s" constraint is no longer supported.', static::class));
        }

        $this->constraints = $this->getConstraints([]);

        parent::__construct($options, $groups, $payload);
    }

    final protected function getCompositeOption(): string
    {
        return 'constraints';
    }

    final public function validatedBy(): string
    {
        return CompoundValidator::class;
    }

    /**
     * @param array<string, mixed> $options
     *
     * @return Constraint[]
     */
    abstract protected function getConstraints(array $options): array;
}
