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

use Symfony\Component\DependencyInjection\Exception\LogicException;
use Symfony\Component\Workflow\Arc;
use Symfony\Component\Workflow\Attribute\AsWorkflow;
use Symfony\Component\Workflow\Attribute\Place;
use Symfony\Component\Workflow\Attribute\Transition;
use Symfony\Component\Workflow\Validator\DefinitionValidatorInterface;
use Symfony\Component\Workflow\WorkflowEvents;

/**
 * Reads the definition of a workflow from the attributes of a class.
 *
 * @author Grégoire Pineau <lyrixx@lyrixx.info>
 *
 * @internal
 */
final class AttributeReader
{
    public function read(AsWorkflow $attribute, \ReflectionClass $class): WorkflowDescriptor
    {
        $className = $class->name;

        if ('' === $name = $attribute->name ?? self::deriveName($className)) {
            throw new LogicException(\sprintf('The name of the workflow defined by "%s" cannot be empty.', $className));
        }

        $supports = \is_array($attribute->supports) ? $attribute->supports : [$attribute->supports];
        foreach ($supports as $supportedClass) {
            if (!\is_string($supportedClass) || !class_exists($supportedClass) && !interface_exists($supportedClass, false)) {
                throw new LogicException(\sprintf('The supported class or interface "%s" of the workflow defined by "%s" does not exist.', \is_string($supportedClass) ? $supportedClass : get_debug_type($supportedClass), $className));
            }
        }
        if ($supports && null !== $attribute->supportStrategy) {
            throw new LogicException(\sprintf('The "supports" and "supportStrategy" arguments of "#[%s]" cannot be used together on "%s".', AsWorkflow::class, $className));
        }
        if (null !== $attribute->markingProperty && null !== $attribute->markingStore) {
            throw new LogicException(\sprintf('The "markingProperty" and "markingStore" arguments of "#[%s]" cannot be used together on "%s".', AsWorkflow::class, $className));
        }
        $this->validateEventsToDispatch($attribute->eventsToDispatch, $className);
        $this->validateDefinitionValidators($attribute->definitionValidators, $className);

        // The string-backed enums used by the definition, whose cases may carry a #[Place] attribute
        $enums = [];
        // The names of the places as keys, their metadata as values (null when not defined explicitly)
        $places = [];
        $placesEnum = null;

        if (\is_string($attribute->places)) {
            $placesEnum = $attribute->places;
            if (!enum_exists($placesEnum) || !is_subclass_of($placesEnum, \BackedEnum::class)) {
                throw new LogicException(\sprintf('The "places" argument of "#[%s]" on "%s" must be a list of places or the name of a string-backed enum, "%s" given.', AsWorkflow::class, $className, $placesEnum));
            }
            foreach ($placesEnum::cases() as $case) {
                $places[$this->getPlaceName($case, $className, $enums)] = null;
            }
        } else {
            foreach ($attribute->places as $place) {
                if ($place instanceof Place) {
                    if (null === $place->name) {
                        throw new LogicException(\sprintf('The "name" argument of "%s" is required when it is used in the "places" argument of "#[%s]" on "%s".', Place::class, AsWorkflow::class, $className));
                    }
                    $placeName = $this->getPlaceName($place->name, $className, $enums);
                    $metadata = $place->metadata;
                } elseif ($place instanceof \BackedEnum || \is_string($place)) {
                    $placeName = $this->getPlaceName($place, $className, $enums);
                    $metadata = null;
                } else {
                    throw new LogicException(\sprintf('The "places" argument of "#[%s]" on "%s" must be a list of "%s" instances, enum cases or strings, "%s" given.', AsWorkflow::class, $className, Place::class, get_debug_type($place)));
                }

                if (\array_key_exists($placeName, $places)) {
                    throw new LogicException(\sprintf('The place "%s" is defined twice in the "places" argument of "#[%s]" on "%s".', $placeName, AsWorkflow::class, $className));
                }
                $places[$placeName] = $metadata;
            }
        }

        $transitions = [];
        foreach ($attribute->transitions as $transition) {
            if (!$transition instanceof Transition) {
                throw new LogicException(\sprintf('The "transitions" argument of "#[%s]" on "%s" must be a list of "%s" instances, "%s" given.', AsWorkflow::class, $className, Transition::class, get_debug_type($transition)));
            }
            if (null === $transition->name || '' === $transition->name) {
                throw new LogicException(\sprintf('The "name" argument of "%s" is required when it is used in the "transitions" argument of "#[%s]" on "%s".', Transition::class, AsWorkflow::class, $className));
            }
            $transitions[] = $this->createTransition($transition->name, $transition, $className, $enums);
        }
        foreach ($class->getReflectionConstants() as $constant) {
            foreach ($constant->getAttributes(Transition::class) as $reflectionAttribute) {
                $transition = $reflectionAttribute->newInstance();
                if (null !== $transition->name) {
                    throw new LogicException(\sprintf('The "name" argument of "#[%s]" cannot be used on "%s::%s": the value of the constant is the name of the transition.', Transition::class, $constant->class, $constant->name));
                }
                $transitionName = $constant->getValue();
                if (!\is_string($transitionName) || '' === $transitionName) {
                    throw new LogicException(\sprintf('The value of "%s::%s" must be a non-empty string to be used as the name of a transition, "%s" given.', $constant->class, $constant->name, \is_string($transitionName) ? $transitionName : get_debug_type($transitionName)));
                }
                $transitions[] = $this->createTransition($transitionName, $transition, $className, $enums);
            }
        }
        if (!$transitions) {
            throw new LogicException(\sprintf('The workflow defined by "%s" must define at least one transition.', $className));
        }

        // Infer the places from the transitions
        foreach ($transitions as $transition) {
            foreach ([...$transition->from, ...$transition->to] as $arc) {
                if (!\array_key_exists($arc->place, $places)) {
                    $places[$arc->place] = null;
                }
            }
        }

        foreach ($places as $placeName => $metadata) {
            if (null !== $placesEnum && null === $placesEnum::tryFrom($placeName)) {
                throw new LogicException(\sprintf('The place "%s" of the workflow defined by "%s" is not a case of "%s".', $placeName, $className, $placesEnum));
            }
            $places[$placeName] = $metadata ?? $this->readPlaceMetadata((string) $placeName, $enums);
        }

        $initialMarking = [];
        $initialPlaces = null === $attribute->initialMarking ? [] : (\is_array($attribute->initialMarking) ? $attribute->initialMarking : [$attribute->initialMarking]);
        foreach ($initialPlaces as $place) {
            if (!$place instanceof \BackedEnum && !\is_string($place)) {
                throw new LogicException(\sprintf('The "initialMarking" argument of "#[%s]" on "%s" must be a list of enum cases or strings, "%s" given.', AsWorkflow::class, $className, get_debug_type($place)));
            }
            $placeName = $this->getPlaceName($place, $className, $enums);
            if (!\array_key_exists($placeName, $places)) {
                throw new LogicException(\sprintf('The initial place "%s" of the workflow defined by "%s" is not a place of the workflow.', $placeName, $className));
            }
            $initialMarking[] = $placeName;
        }

        return new WorkflowDescriptor(
            $name,
            $attribute->type,
            $places,
            $transitions,
            $initialMarking,
            $supports,
            $attribute->supportStrategy,
            $attribute->markingProperty,
            $attribute->markingStore,
            $attribute->metadata,
            $attribute->auditTrail,
            $attribute->eventsToDispatch,
            $attribute->definitionValidators,
        );
    }

    /**
     * Derives the name of a workflow from the name of the class defining it:
     * "App\Workflow\PullRequestStateMachine" gives "pull_request".
     */
    private static function deriveName(string $class): string
    {
        $name = substr($class, (int) strrpos($class, '\\'));
        $name = ltrim($name, '\\');
        $name = preg_replace('/(?<=.)(Workflow|StateMachine)$/', '', $name);

        return strtolower(preg_replace(['/([A-Z]+)([A-Z][a-z])/', '/([a-z\d])([A-Z])/'], ['\1_\2', '\1_\2'], $name));
    }

    /**
     * @param array<class-string<\BackedEnum>, class-string<\BackedEnum>> $enums
     */
    private function createTransition(string $name, Transition $transition, string $class, array &$enums): TransitionDescriptor
    {
        if ('' === $transition->guard) {
            throw new LogicException(\sprintf('The "guard" argument of the "%s" transition of the workflow defined by "%s" cannot be empty.', $name, $class));
        }

        return new TransitionDescriptor(
            $name,
            $this->createArcs($transition->from, $name, 'from', $class, $enums),
            $this->createArcs($transition->to, $name, 'to', $class, $enums),
            $transition->guard,
            $transition->metadata,
        );
    }

    /**
     * @param \BackedEnum|Arc|string|array<\BackedEnum|Arc|string>        $places
     * @param array<class-string<\BackedEnum>, class-string<\BackedEnum>> $enums
     *
     * @return list<Arc>
     */
    private function createArcs(\BackedEnum|Arc|string|array $places, string $transition, string $argument, string $class, array &$enums): array
    {
        $arcs = [];
        foreach (\is_array($places) ? $places : [$places] as $place) {
            if ($place instanceof Arc) {
                $arcs[] = $place;
            } elseif ($place instanceof \BackedEnum || \is_string($place)) {
                $arcs[] = new Arc($this->getPlaceName($place, $class, $enums), 1);
            } else {
                throw new LogicException(\sprintf('The "%s" argument of the "%s" transition of the workflow defined by "%s" must be a list of "%s" instances, enum cases or strings, "%s" given.', $argument, $transition, $class, Arc::class, get_debug_type($place)));
            }
        }
        if (!$arcs) {
            throw new LogicException(\sprintf('The "%s" argument of the "%s" transition of the workflow defined by "%s" cannot be empty.', $argument, $transition, $class));
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
     * @param array<class-string<\BackedEnum>, class-string<\BackedEnum>> $enums
     *
     * @return array<string, mixed>
     */
    private function readPlaceMetadata(string $place, array $enums): array
    {
        foreach ($enums as $enum) {
            if (null === $case = $enum::tryFrom($place)) {
                continue;
            }
            $attribute = (new \ReflectionEnumBackedCase($enum, $case->name))->getAttributes(Place::class)[0] ?? null;
            if (!$attribute) {
                return [];
            }
            $attribute = $attribute->newInstance();
            if (null !== $attribute->name) {
                throw new LogicException(\sprintf('The "name" argument of "#[%s]" cannot be used on "%s::%s": the value of the case is the name of the place.', Place::class, $enum, $case->name));
            }

            return $attribute->metadata;
        }

        return [];
    }

    /**
     * @param list<string>|null $events
     */
    private function validateEventsToDispatch(?array $events, string $class): void
    {
        if (null === $events) {
            return;
        }

        $hasAllowList = false;
        $hasBlockList = false;
        foreach ($events as $event) {
            if (!\is_string($event)) {
                throw new LogicException(\sprintf('The "eventsToDispatch" argument of "#[%s]" on "%s" must be a list of event names, "%s" given.', AsWorkflow::class, $class, get_debug_type($event)));
            }
            $blocked = str_starts_with($event, '!');
            $eventName = $blocked ? substr($event, 1) : $event;
            if (!\in_array($eventName, WorkflowEvents::ALIASES, true)) {
                throw new LogicException(\sprintf('The "eventsToDispatch" argument of "#[%s]" on "%s" must be a list of workflow events (like "workflow.enter"), "%s" given.', AsWorkflow::class, $class, $event));
            }
            if ($blocked && WorkflowEvents::GUARD === $eventName) {
                throw new LogicException(\sprintf('The "%s" event cannot be disabled in the "eventsToDispatch" argument of "#[%s]" on "%s": it is always dispatched.', WorkflowEvents::GUARD, AsWorkflow::class, $class));
            }
            $hasAllowList = $hasAllowList || !$blocked;
            $hasBlockList = $hasBlockList || $blocked;
        }

        if ($hasAllowList && $hasBlockList) {
            throw new LogicException(\sprintf('Cannot mix allow-list and block-list entries in the "eventsToDispatch" argument of "#[%s]" on "%s": every entry must start with "!" (block-list mode) or none of them must (allow-list mode).', AsWorkflow::class, $class));
        }
    }

    /**
     * @param list<class-string<DefinitionValidatorInterface>> $validators
     */
    private function validateDefinitionValidators(array $validators, string $class): void
    {
        foreach ($validators as $validator) {
            if (!\is_string($validator) || !class_exists($validator)) {
                throw new LogicException(\sprintf('The definition validator "%s" of the workflow defined by "%s" does not exist.', \is_string($validator) ? $validator : get_debug_type($validator), $class));
            }
            if (!is_a($validator, DefinitionValidatorInterface::class, true)) {
                throw new LogicException(\sprintf('The definition validator "%s" of the workflow defined by "%s" must implement "%s".', $validator, $class, DefinitionValidatorInterface::class));
            }
            if ((new \ReflectionClass($validator))->getConstructor()?->getNumberOfRequiredParameters()) {
                throw new LogicException(\sprintf('The constructor of the definition validator "%s" of the workflow defined by "%s" must not have any required argument.', $validator, $class));
            }
        }
    }
}
