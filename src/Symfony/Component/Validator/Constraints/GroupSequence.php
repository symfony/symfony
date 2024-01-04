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
 * Group sequences can also be used to override the "Default" validation group for a class. When a class has an
 * associated group sequence and is validated in the "Default" group, the group sequence is applied instead.
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
