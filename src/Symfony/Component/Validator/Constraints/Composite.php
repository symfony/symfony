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
use Symfony\Component\Validator\Exception\ConstraintDefinitionException;
use Symfony\Component\Validator\Exception\InvalidArgumentException;

/**
 * A constraint that is composed of other constraints.
 *
 * You should never use the nested constraint instances anywhere else, because
 * their groups are adapted when passed to the constructor of this class.
 *
 * If you want to create your own composite constraint, extend this class and
 * let {@link getCompositeOption()} return the name of the property which
 * contains the nested constraints.
 *
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
abstract class Composite extends Constraint
{
    /**
     * The groups of the composite and its nested constraints are made
     * consistent using the following strategy:
     *
     *   - If groups are passed explicitly to the composite constraint, but
     *     not to the nested constraints, the options of the composite
     *     constraint are copied to the nested constraints;
     *
     *   - If groups are passed explicitly to the nested constraints, but not
     *     to the composite constraint, the groups of all nested constraints
     *     are merged and used as groups for the composite constraint;
     *
     *   - If groups are passed explicitly to both the composite and its nested
     *     constraints, the groups of the nested constraints must be a subset
     *     of the groups of the composite constraint. If not, a
     *     {@link ConstraintDefinitionException} is thrown.
     *
     * All this is done in the constructor, because constraints can then be
     * cached. When constraints are loaded from the cache, no more group
     * checks need to be done.
     */
    public function __construct(mixed $options = null, ?array $groups = null, mixed $payload = null)
    {
        if (null !== $options) {
            throw new InvalidArgumentException(\sprintf('Passing an array of options to configure the "%s" constraint is no longer supported.', static::class));
        }

        parent::__construct(null, $groups, $payload);

        $this->initializeNestedConstraints();

        foreach ((array) $this->getCompositeOption() as $option) {
            /** @var Constraint[] $nestedConstraints */
            $nestedConstraints = $this->$option;

            if (!\is_array($nestedConstraints)) {
                $nestedConstraints = [$nestedConstraints];
            }

            foreach ($nestedConstraints as $constraint) {
                if (!$constraint instanceof Constraint) {
                    if (\is_object($constraint)) {
                        $constraint = get_debug_type($constraint);
                    }

                    throw new ConstraintDefinitionException(\sprintf('The value "%s" is not an instance of Constraint in constraint "%s".', $constraint, get_debug_type($this)));
                }

                if ($constraint instanceof Valid) {
                    throw new ConstraintDefinitionException(\sprintf('The constraint Valid cannot be nested inside constraint "%s". You can only declare the Valid constraint directly on a field or method.', get_debug_type($this)));
                }
            }

            if (!isset(((array) $this)['groups'])) {
                $mergedGroups = [];

                foreach ($nestedConstraints as $constraint) {
                    foreach ($constraint->groups as $group) {
                        $mergedGroups[$group] = true;
                    }
                }

                // prevent empty composite constraint to have empty groups
                $this->groups = array_keys($mergedGroups) ?: [self::DEFAULT_GROUP];
                $this->$option = $nestedConstraints;

                continue;
            }

            foreach ($nestedConstraints as $constraint) {
                if (isset(((array) $constraint)['groups'])) {
                    $excessGroups = array_diff($constraint->groups, $this->groups);

                    if (\count($excessGroups) > 0) {
                        throw new ConstraintDefinitionException(\sprintf('The group(s) "%s" passed to the constraint "%s" should also be passed to its containing constraint "%s".', implode('", "', $excessGroups), get_debug_type($constraint), get_debug_type($this)));
                    }
                } else {
                    $constraint->groups = $this->groups;
                }
            }

            $this->$option = $nestedConstraints;
        }
    }

    /**
     * Implicit group names are forwarded to nested constraints.
     */
    public function addImplicitGroupName(string $group): void
    {
        parent::addImplicitGroupName($group);

        foreach ((array) $this->getCompositeOption() as $option) {
            /* @var Constraint[] $nestedConstraints */
            $nestedConstraints = (array) $this->$option;

            foreach ($nestedConstraints as $constraint) {
                $constraint->addImplicitGroupName($group);
            }
        }
    }

    /**
     * Returns the name of the property that contains the nested constraints.
     */
    abstract protected function getCompositeOption(): array|string;

    /**
     * @internal Used by metadata
     *
     * @return Constraint[]
     */
    public function getNestedConstraints(): array
    {
        $constraints = [];
        foreach ((array) $this->getCompositeOption() as $option) {
            $constraints = array_merge($constraints, (array) $this->$option);
        }

        return $constraints;
    }

    /**
     * Initializes the nested constraints.
     *
     * This method can be overwritten in subclasses to clean up the nested
     * constraints passed to the constructor.
     *
     * @see Collection::initializeNestedConstraints()
     */
    protected function initializeNestedConstraints(): void
    {
    }
}
