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

use Symfony\Component\Workflow\Validator\DefinitionValidatorInterface;
use Symfony\Component\Workflow\WorkflowType;

/**
 * Defines a workflow from a string-backed enum whose cases are places.
 *
 * @author Antonio Pauletich <antonio.pauletich95@gmail.com>
 */
#[\Attribute(\Attribute::TARGET_CLASS)]
final class AsWorkflowDefinition
{
    /**
     * @param list<class-string>                               $supports
     * @param \BackedEnum|list<\BackedEnum>|null               $initialMarking
     * @param list<string>|null                                $eventsToDispatch
     * @param array<string, mixed>                             $metadata
     * @param list<class-string<DefinitionValidatorInterface>> $definitionValidators
     */
    public function __construct(
        public readonly string $name,
        public readonly WorkflowType $type = WorkflowType::StateMachine,
        public readonly array $supports = [],
        public readonly ?string $supportStrategy = null,
        public readonly \BackedEnum|array|null $initialMarking = null,
        public readonly ?string $markingStoreProperty = null,
        public readonly ?string $markingStoreService = null,
        public readonly bool $auditTrail = false,
        public readonly ?array $eventsToDispatch = null,
        public readonly array $metadata = [],
        public readonly array $definitionValidators = [],
    ) {
    }
}
