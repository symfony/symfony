<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Workflow\Attribute;

use Symfony\Component\Workflow\Arc;

/**
 * Defines a transition of a workflow defined with the AsWorkflow attribute.
 *
 * Use it on the constants of the class, whose values are used as the names
 * of the transitions, or in the "transitions" argument of the AsWorkflow
 * attribute. Repeat it on a constant to define several transitions with the
 * same name.
 *
 * @author Grégoire Pineau <lyrixx@lyrixx.info>
 */
#[\Attribute(\Attribute::TARGET_CLASS_CONSTANT | \Attribute::IS_REPEATABLE)]
final class Transition
{
    /**
     * @param string|null                                         $name     The name of the transition; must be omitted when the attribute is used on a constant, whose value is then used
     * @param \BackedEnum|Arc|string|list<\BackedEnum|Arc|string> $from     The input place(s) of the transition, using an Arc to set a weight
     * @param \BackedEnum|Arc|string|list<\BackedEnum|Arc|string> $to       The output place(s) of the transition, using an Arc to set a weight
     * @param string|null                                         $guard    An expression that must be true to enable the transition
     * @param array<string, mixed>                                $metadata The metadata of the transition
     */
    public function __construct(
        public readonly ?string $name = null,
        public readonly \BackedEnum|Arc|string|array $from = [],
        public readonly \BackedEnum|Arc|string|array $to = [],
        public readonly ?string $guard = null,
        public readonly array $metadata = [],
    ) {
    }
}
