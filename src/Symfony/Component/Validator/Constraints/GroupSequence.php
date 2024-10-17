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
 * The GroupSequence class represents a sequence of validation groups.
 * It is used to enforce a specific order in which validation groups should be processed.
 *
 * When validating a group sequence, each group is validated sequentially. A group is only validated if all
 * previous groups in the sequence succeeded. This approach is beneficial for scenarios where certain validation
 * groups are more resource-intensive or rely on the success of prior validations.
 *
 * For example, when validating an address:
 *
 *     $validator->validate($address, null, new GroupSequence(['Basic', 'Strict']));
 *
 * In this case, all constraints in the "Basic" group are validated first. If none of the "Basic" constraints fail,
 * the "Strict" group constraints are then validated. This is useful if, for instance, the "Strict" group contains
 * more resource-intensive checks.
 *
 * Group sequences can also be used to override the "Default" validation group for a class:
 *
 *     #[GroupSequence(['Address', 'Strict'])]
 *     class Address
 *     {
 *         // ...
 *     }
 *
 * When you validate the `Address` object in the "Default" group, the specified group sequence is applied:
 *
 *     $validator->validate($address);
 *
 * To validate the constraints of the "Default" group for a class with an overridden default group,
 * pass the class name as the group name:
 *
 *     $validator->validate($address, null, "Address")
 *
 * This feature allows for fine-grained control over the validation process, ensuring efficient and effective
 * validation flows.
 *
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
#[\Attribute(\Attribute::TARGET_CLASS)]
class GroupSequence
{
    /**
     * An array of groups that make up the sequence. The validation process will adhere to this order.
     * Each element can be a string representing a single group, an array of groups, or another GroupSequence.
     *
     * @var array<int, string|string[]|GroupSequence>
     */
    public array $groups;

    /**
     * Specifies the group that will be used for cascaded validation.
     * By default, all groups in the sequence are used for cascading.
     * If a group sequence is attached to a class, replacing the "Default" group,
     * this property allows specifying an alternate group for cascading validations.
     *
     * @var string|GroupSequence
     */
    public string|GroupSequence $cascadedGroup;

    /**
     * Constructs a new GroupSequence with the specified sequence of groups.
     *
     * @param array<string|string[]|GroupSequence> $groups The groups in the sequence
     */
    public function __construct(array $groups)
    {
        $this->groups = $groups['value'] ?? $groups;
    }
}
