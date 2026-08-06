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

/**
 * A sequence of validation groups.
 *
 * When validating a group sequence, each group will only be validated if all
 * of the previous groups in the sequence succeeded. For example:
 *
 *     $validator->validate($address, null, new GroupSequence(['Basic', 'Strict']));
 *
 * In the first step, all constraints that belong to the group "Basic" will be
 * validated. If none of the constraints fail, the validator will then validate
 * the constraints in group "Strict". This is useful, for example, if "Strict"
 * contains expensive checks that require a lot of CPU or slow, external
 * services. You usually don't want to run expensive checks if any of the cheap
 * checks fail.
 *
 * When adding metadata to a class, you can override the "Default" group of
 * that class with a group sequence:
 *     #[GroupSequence(['Address', 'Strict'])]
 *     class Address
 *     {
 *         // ...
 *     }
 *
 * Whenever you validate that object in the "Default" group, the group sequence
 * will be validated:
 *
 *     $validator->validate($address);
 *
 * If you want to execute the constraints of the "Default" group for a class
 * with an overridden default group, pass the class name as group name instead:
 *
 *     $validator->validate($address, null, "Address")
 *
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
#[\Attribute(\Attribute::TARGET_CLASS)]
class GroupSequence
{
    /**
     * The groups in the sequence.
     *
     * @var array<int, string|string[]|GroupSequence>
     */
    public array $groups;

    /**
     * Whether the group currently being stepped through must be cascaded to
     * referenced objects, in addition to the "Default" group.
     *
     * When a class declares a group sequence (through the {@see GroupSequence}
     * attribute, {@see \Symfony\Component\Validator\Mapping\ClassMetadata::setGroupSequence()}
     * or {@see \Symfony\Component\Validator\GroupSequenceProviderInterface}),
     * referenced objects marked with the {@see Valid} constraint are, by default,
     * only cascaded in the "Default" group. As a result, constraints registered
     * on those objects for one of the sequence groups are never validated.
     *
     * Setting this flag to "true" makes the validator cascade the group of the
     * sequence currently being stepped through together with the "Default" group,
     * so that such constraints are validated as well.
     *
     * The flag only applies to sequences declared on the class: when a sequence
     * is passed explicitly to the validator, the group being stepped through is
     * already the one cascaded to referenced objects.
     */
    public bool $cascadeCurrentGroup = false;

    /**
     * Creates a new group sequence.
     *
     * @param array<string|string[]|GroupSequence> $groups              The groups in the sequence
     * @param bool                                 $cascadeCurrentGroup Whether to also cascade the current sequence
     *                                                                  group to referenced objects, on top of "Default"
     */
    public function __construct(array $groups, bool $cascadeCurrentGroup = false)
    {
        $this->groups = $groups;
        $this->cascadeCurrentGroup = $cascadeCurrentGroup;
    }
}
