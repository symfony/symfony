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

use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Exception\LogicException;
use Symfony\Component\Workflow\Arc;
use Symfony\Component\Workflow\Attribute\AsWorkflow;
use Symfony\Component\Workflow\Attribute\Place;
use Symfony\Component\Workflow\Attribute\Transition;
use Symfony\Component\Workflow\WorkflowType;

/**
 * Reads the definition of a workflow from the attributes of a class.
 *
 * The definition is returned in the format of the "workflow.workflows" configuration, which validates it.
 *
 * @author Grégoire Pineau <lyrixx@lyrixx.info>
 *
 * @internal
 */
final class AttributeReader
{
    /**
     * @param ContainerBuilder|null $container When set, the enums used by the definition are tracked as resources of the container
     *
     * @return array<string, array<string, mixed>> The configuration of the workflow, keyed by its name
     */
    public function read(AsWorkflow $attribute, \ReflectionClass $class, ?ContainerBuilder $container = null): array
    {
        $className = $class->name;

        if ('' === $name = $attribute->name ?? self::deriveName($className)) {
            throw new LogicException(\sprintf('The name of the workflow defined by "%s" cannot be empty.', $className));
        }

        // The string-backed enums used by the definition, whose cases may carry a #[Place] attribute
        $enums = [];
        // The names of the places as keys, the #[Place] attribute of their constant as values (null when there is none)
        $places = [];

        if (null !== $placesEnum = $attribute->places) {
            if (!is_subclass_of($placesEnum, \BackedEnum::class)) {
                throw new LogicException(\sprintf('The "places" argument of "#[%s]" on "%s" must be the name of a string-backed enum, "%s" given.', AsWorkflow::class, $className, $placesEnum));
            }
            foreach ($placesEnum::cases() as $case) {
                $places[$this->getPlaceName($case, $className, $enums)] = null;
            }
        }

        $transitions = [];
        $placeConstants = [];
        foreach ($class->getReflectionConstants() as $constant) {
            foreach ($constant->getAttributes(Transition::class, \ReflectionAttribute::IS_INSTANCEOF) as $reflectionAttribute) {
                if (!\is_string($transitionName = $constant->getValue())) {
                    throw new LogicException(\sprintf('The value of "%s::%s" must be a string to be used as the name of a transition, "%s" given.', $constant->class, $constant->name, get_debug_type($transitionName)));
                }
                $transition = $reflectionAttribute->newInstance();
                $transitionConfig = [
                    'name' => $transitionName,
                    'from' => $this->createArcs($transition->from, $transitionName, $className, $enums),
                    'to' => $this->createArcs($transition->to, $transitionName, $className, $enums),
                    'metadata' => $transition->metadata,
                ];
                if (WorkflowType::StateMachine === $attribute->type && (1 < \count($transitionConfig['from']) || 1 < \count($transitionConfig['to']))) {
                    throw new LogicException(\sprintf('The "%s" transition of the state machine defined by "%s" must have exactly one input and one output place; repeat the "#[%s]" attribute to define several transitions with the same name.', $transitionName, $className, Transition::class));
                }
                if (null !== $transition->guard) {
                    $transitionConfig['guard'] = $transition->guard;
                }
                $transitions[] = $transitionConfig;
            }

            if ($placeAttribute = $this->getPlaceAttribute($constant)) {
                if (!\is_string($placeName = $constant->getValue()) && !$placeName instanceof \BackedEnum) {
                    throw new LogicException(\sprintf('The value of "%s::%s" must be a string or a string-backed enum case to be used as the name of a place, "%s" given.', $constant->class, $constant->name, get_debug_type($placeName)));
                }
                $placeName = $this->getPlaceName($placeName, $className, $enums);
                if (isset($placeConstants[$placeName])) {
                    throw new LogicException(\sprintf('The place "%s" of the workflow defined by "%s" is defined by both "%s" and "%s::%s".', $placeName, $className, $placeConstants[$placeName][0], $constant->class, $constant->name));
                }
                $placeConstants[$placeName] = [$constant->class.'::'.$constant->name, $placeAttribute->newInstance()];
            }
        }

        // Infer the places from the transitions, then add the places that no transition uses
        foreach ($transitions as $transition) {
            foreach ([...$transition['from'], ...$transition['to']] as $arc) {
                $places[$arc['place']] ??= null;
            }
        }
        foreach ($placeConstants as $placeName => [, $placeAttribute]) {
            $places[$placeName] ??= $placeAttribute;
        }

        $placesConfig = [];
        $initialPlaces = [];
        foreach ($places as $placeName => $constantAttribute) {
            $placeName = (string) $placeName;
            if (null !== $placesEnum && null === $placesEnum::tryFrom($placeName)) {
                throw new LogicException(\sprintf('The place "%s" of the workflow defined by "%s" is not a case of "%s".', $placeName, $className, $placesEnum));
            }
            $caseAttribute = $this->readEnumCaseAttribute($placeName, $enums);
            $placesConfig[] = ['name' => $placeName, 'metadata' => $constantAttribute?->metadata ?: $caseAttribute->metadata ?? []];
            if ($constantAttribute?->initial || $caseAttribute?->initial) {
                $initialPlaces[] = $placeName;
            }
        }

        foreach ($enums as $enum) {
            // Changing the cases of the enums or their #[Place] attributes must invalidate the container
            $container?->getReflectionClass($enum);
        }

        $config = [
            'type' => $attribute->type->value,
            'supports' => $attribute->supports,
            'initial_marking' => $attribute->initialMarking ?? $initialPlaces,
            'metadata' => $attribute->metadata,
            'audit_trail' => ['enabled' => $attribute->auditTrail],
            'definition_validators' => $attribute->definitionValidators,
            'places' => $placesConfig,
            'transitions' => $transitions,
        ];
        if (null !== $attribute->supportStrategy) {
            $config['support_strategy'] = $attribute->supportStrategy;
        }
        if (null !== $attribute->markingProperty) {
            $config['marking_store'] = ['property' => $attribute->markingProperty];
        } elseif (null !== $attribute->markingStore) {
            $config['marking_store'] = ['service' => $attribute->markingStore];
        }
        if (null !== $attribute->eventsToDispatch) {
            $config['events_to_dispatch'] = $attribute->eventsToDispatch;
        }

        return [$name => $config];
    }

    /**
     * Derives the name of a workflow from the name of the class defining it: "App\Workflow\PullRequestStateMachine" gives "pull_request".
     */
    private static function deriveName(string $class): string
    {
        $name = substr($class, (int) strrpos($class, '\\'));
        $name = ltrim($name, '\\');
        $name = preg_replace('/(?<=.)(Workflow|StateMachine)$/', '', $name);

        return strtolower(preg_replace(['/([A-Z]+)([A-Z][a-z])/', '/([a-z\d])([A-Z])/'], ['\1_\2', '\1_\2'], $name));
    }

    /**
     * @param \BackedEnum|Arc|string|array<\BackedEnum|Arc|string>        $places
     * @param array<class-string<\BackedEnum>, class-string<\BackedEnum>> $enums
     *
     * @return list<array{place: string, weight?: int}>
     */
    private function createArcs(\BackedEnum|Arc|string|array $places, string $transition, string $class, array &$enums): array
    {
        $arcs = [];
        foreach (\is_array($places) ? $places : [$places] as $place) {
            $arcs[] = match (true) {
                $place instanceof Arc => ['place' => $place->place, 'weight' => $place->weight],
                $place instanceof \BackedEnum, \is_string($place) => ['place' => $this->getPlaceName($place, $class, $enums)],
                default => throw new LogicException(\sprintf('The places of the "%s" transition of the workflow defined by "%s" must be "%s" instances, enum cases or strings, "%s" given.', $transition, $class, Arc::class, get_debug_type($place))),
            };
        }

        return $arcs;
    }

    /**
     * @param array<class-string<\BackedEnum>, class-string<\BackedEnum>> $enums
     */
    private function getPlaceName(\BackedEnum|string $place, string $class, array &$enums): string
    {
        if (\is_string($place)) {
            return $place;
        }
        if (!\is_string($place->value)) {
            throw new LogicException(\sprintf('Only string-backed enums can be used as places of the workflow defined by "%s", "%s" is not.', $class, $place::class));
        }
        $enums[$place::class] = $place::class;

        return $place->value;
    }

    /**
     * Reads the #[Place] attribute of the enum case of a place.
     *
     * @param array<class-string<\BackedEnum>, class-string<\BackedEnum>> $enums
     */
    private function readEnumCaseAttribute(string $place, array $enums): ?Place
    {
        foreach ($enums as $enum) {
            if (null !== $case = $enum::tryFrom($place)) {
                return $this->getPlaceAttribute(new \ReflectionEnumBackedCase($enum, $case->name))?->newInstance();
            }
        }

        return null;
    }

    /**
     * @return \ReflectionAttribute<Place>|null
     */
    private function getPlaceAttribute(\ReflectionClassConstant $constant): ?\ReflectionAttribute
    {
        $attributes = $constant->getAttributes(Place::class, \ReflectionAttribute::IS_INSTANCEOF);
        if (1 < \count($attributes)) {
            throw new LogicException(\sprintf('"%s::%s" cannot have several "#[%s]" attributes, including the ones extending it.', $constant->class, $constant->name, Place::class));
        }

        return $attributes[0] ?? null;
    }
}
