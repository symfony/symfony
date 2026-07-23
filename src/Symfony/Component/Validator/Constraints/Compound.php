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

        if (null !== $groups) {
            // reset nested groups so that Composite::__construct() does not run its subset check
            self::resetNestedConstraintsGroups($this->constraints);
        }

        parent::__construct($options, $groups, $payload);

        if (null !== $groups) {
            self::propagateGroupsToNestedConstraints($this->constraints, $this->groups);
        }
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

    private static function resetNestedConstraintsGroups(array $constraints): void
    {
        foreach ($constraints as $constraint) {
            if (!$constraint instanceof Constraint) {
                continue;
            }
            if ($constraint instanceof Composite) {
                // skip Composites with explicit groups: their subset check is the user's contract
                if ([Constraint::DEFAULT_GROUP] !== $constraint->groups) {
                    continue;
                }
                self::resetNestedConstraintsGroups($constraint->getNestedConstraints());
            }
            unset($constraint->groups);
        }
    }

    private static function propagateGroupsToNestedConstraints(array $constraints, array $groups): void
    {
        foreach ($constraints as $constraint) {
            if (!$constraint instanceof Constraint) {
                continue;
            }
            if ($constraint instanceof Composite && $groups !== $constraint->groups) {
                continue;
            }
            $constraint->groups = $groups;
            if ($constraint instanceof Composite) {
                self::propagateGroupsToNestedConstraints($constraint->getNestedConstraints(), $groups);
            }
        }
    }
}
