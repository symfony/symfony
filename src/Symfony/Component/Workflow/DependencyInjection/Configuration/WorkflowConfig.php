<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Workflow\DependencyInjection\Configuration;

use Symfony\Component\Workflow\Validator\DefinitionValidatorInterface;
use Symfony\Component\Workflow\WorkflowType;

/**
 * @author Antonio Pauletich <antonio.pauletich95@gmail.com>
 *
 * @internal
 */
final class WorkflowConfig
{
    /**
     * @param list<PlaceConfig>                                $places
     * @param list<TransitionConfig>                           $transitions
     * @param list<string>                                     $initialMarking
     * @param list<class-string>                               $supports
     * @param array<string, mixed>                             $metadata
     * @param list<string>|null                                $eventsToDispatch
     * @param list<class-string<DefinitionValidatorInterface>> $definitionValidators
     */
    public function __construct(
        public readonly string $name,
        public readonly WorkflowType $type,
        public readonly array $places,
        public readonly array $transitions,
        public readonly array $initialMarking,
        public readonly array $supports,
        public readonly ?string $supportStrategy,
        public readonly array $metadata,
        public readonly ?string $markingStoreProperty,
        public readonly ?string $markingStoreService,
        public readonly bool $auditTrail,
        public readonly ?array $eventsToDispatch,
        public readonly array $definitionValidators,
    ) {
    }

    /**
     * @param array{
     *     type: string,
     *     places: list<array{name: string, metadata: array<string, mixed>}>,
     *     transitions: array<array-key, array{
     *         name: string,
     *         from: list<array{place: string, weight: int}>,
     *         to: list<array{place: string, weight: int}>,
     *         guard?: string,
     *         metadata: array<string, mixed>,
     *     }>,
     *     initial_marking: list<string>,
     *     supports: list<class-string>,
     *     support_strategy?: string,
     *     metadata: array<string, mixed>,
     *     marking_store: array{type?: string, property?: string, service?: string},
     *     audit_trail: array{enabled: bool},
     *     events_to_dispatch: list<string>|null,
     *     definition_validators: list<class-string<DefinitionValidatorInterface>>,
     * } $workflow
     */
    public static function fromArray(string $name, array $workflow): self
    {
        $places = array_values(array_map(
            static fn (array $place): PlaceConfig => new PlaceConfig($place['name'], $place['metadata']),
            $workflow['places'],
        ));
        $transitions = array_values(array_map(
            static fn (array $transition): TransitionConfig => new TransitionConfig(
                $transition['name'],
                array_map(static fn (array $arc): ArcConfig => new ArcConfig($arc['place'], $arc['weight']), $transition['from']),
                array_map(static fn (array $arc): ArcConfig => new ArcConfig($arc['place'], $arc['weight']), $transition['to']),
                $transition['guard'] ?? null,
                $transition['metadata'],
            ),
            $workflow['transitions'],
        ));

        $markingStoreProperty = null;
        $markingStoreService = null;
        if (isset($workflow['marking_store']['type']) || isset($workflow['marking_store']['property'])) {
            $markingStoreProperty = $workflow['marking_store']['property'] ?? 'marking';
        } elseif (isset($workflow['marking_store']['service'])) {
            $markingStoreService = $workflow['marking_store']['service'];
        }

        return new self(
            $name,
            WorkflowType::from($workflow['type']),
            $places,
            $transitions,
            $workflow['initial_marking'],
            $workflow['supports'],
            $workflow['support_strategy'] ?? null,
            $workflow['metadata'],
            $markingStoreProperty,
            $markingStoreService,
            $workflow['audit_trail']['enabled'],
            $workflow['events_to_dispatch'],
            $workflow['definition_validators'],
        );
    }
}
