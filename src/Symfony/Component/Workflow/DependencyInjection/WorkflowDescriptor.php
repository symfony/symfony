<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Workflow\DependencyInjection;

use Symfony\Component\Workflow\Validator\DefinitionValidatorInterface;
use Symfony\Component\Workflow\WorkflowType;

/**
 * Describes a workflow to register in the container, whatever the format it was declared with.
 *
 * @author Grégoire Pineau <lyrixx@lyrixx.info>
 *
 * @internal
 */
final class WorkflowDescriptor
{
    /**
     * @param array<string, array<string, mixed>>              $places               The names of the places as keys and their metadata as values
     * @param list<TransitionDescriptor>                       $transitions
     * @param list<string>                                     $initialMarking
     * @param list<class-string>                               $supports
     * @param string|null                                      $supportStrategy      The id of a WorkflowSupportStrategyInterface service
     * @param string|null                                      $markingProperty      The property of the subject read by a MethodMarkingStore, null to use the default marking store
     * @param string|null                                      $markingStore         The id of a MarkingStoreInterface service
     * @param array<string, mixed>                             $metadata
     * @param list<string>|null                                $eventsToDispatch
     * @param list<class-string<DefinitionValidatorInterface>> $definitionValidators
     */
    public function __construct(
        public readonly string $name,
        public readonly WorkflowType $type,
        public readonly array $places,
        public readonly array $transitions,
        public readonly array $initialMarking = [],
        public readonly array $supports = [],
        public readonly ?string $supportStrategy = null,
        public readonly ?string $markingProperty = null,
        public readonly ?string $markingStore = null,
        public readonly array $metadata = [],
        public readonly bool $auditTrail = false,
        public readonly ?array $eventsToDispatch = null,
        public readonly array $definitionValidators = [],
    ) {
    }
}
