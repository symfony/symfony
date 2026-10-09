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

use Symfony\Component\Workflow\Exception\LogicException;
use Symfony\Component\Workflow\Validator\DefinitionValidatorInterface;
use Symfony\Component\Workflow\WorkflowType;

/**
 * Defines a workflow or a state machine from a class.
 *
 * The transitions are defined with the Transition attribute on the constants of the class.
 * The places are inferred from the transitions, and the Place attribute defines places without transitions or the metadata of places.
 * The class is a regular service; when it uses the WorkflowTrait, it exposes the methods of the workflow.
 *
 * @author Grégoire Pineau <lyrixx@lyrixx.info>
 */
#[\Attribute(\Attribute::TARGET_CLASS)]
class AsWorkflow
{
    /**
     * Stands for the name of the workflow in the events listened to by the listeners of a class using this attribute, until it is resolved when the container is compiled.
     *
     * @internal
     */
    public const NAME_PLACEHOLDER = '@self';

    /**
     * @param string|null                                      $name                 The name of the workflow; defaults to the snake_cased short name of the class, without its "Workflow" or "StateMachine" suffix
     * @param WorkflowType                                     $type                 Whether the subject can be in a single place (state machine) or in many places at the same time (workflow)
     * @param class-string|list<class-string>                  $supports             The classes or interfaces of the subjects supported by the workflow; without "supports" nor "supportStrategy", the workflow is not added to the registry and can only be used by injection
     * @param string|null                                      $supportStrategy      The id of a WorkflowSupportStrategyInterface service; cannot be used with "supports"
     * @param \BackedEnum|string|list<\BackedEnum|string>|null $initialMarking       The initial place(s) of the subjects; defaults to the places whose "#[Place]" attribute sets "initial", or else to the first case of the "places" enum, or else to the first input place of the first transition
     * @param string|null                                      $markingProperty      The property or method of the subjects storing their marking, read by a MethodMarkingStore; defaults to "marking"
     * @param string|null                                      $markingStore         The id of a MarkingStoreInterface service; cannot be used with "markingProperty"
     * @param array<string, mixed>                             $metadata             The metadata of the workflow
     * @param bool                                             $auditTrail           Whether to log the transitions
     * @param list<string>|null                                $eventsToDispatch     The events to dispatch, or the events not to dispatch when prefixed with "!"; all the events by default
     * @param list<class-string<DefinitionValidatorInterface>> $definitionValidators The validators of the definition, in addition to the one of the type
     * @param class-string<\BackedEnum>|null                   $places               The string-backed enum that lists the places of the workflow: each case is a place, and each place must be a case
     */
    public function __construct(
        public readonly ?string $name = null,
        public readonly WorkflowType $type = WorkflowType::StateMachine,
        public readonly string|array $supports = [],
        public readonly ?string $supportStrategy = null,
        public readonly \BackedEnum|string|array|null $initialMarking = null,
        public readonly ?string $markingProperty = null,
        public readonly ?string $markingStore = null,
        public readonly array $metadata = [],
        public readonly bool $auditTrail = false,
        public readonly ?array $eventsToDispatch = null,
        public readonly array $definitionValidators = [],
        public readonly ?string $places = null,
    ) {
        if ($supports && null !== $supportStrategy) {
            throw new LogicException(\sprintf('The "supports" and "supportStrategy" arguments of "#[%s]" cannot be used together.', self::class));
        }
        if (null !== $markingProperty && null !== $markingStore) {
            throw new LogicException(\sprintf('The "markingProperty" and "markingStore" arguments of "#[%s]" cannot be used together.', self::class));
        }
    }
}
