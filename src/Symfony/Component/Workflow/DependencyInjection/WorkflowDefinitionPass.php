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

use Symfony\Component\Config\Resource\FileResource;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Exception\LogicException;
use Symfony\Component\Workflow\Attribute\AsWorkflowDefinition;
use Symfony\Component\Workflow\Attribute\Place;
use Symfony\Component\Workflow\Attribute\Transition;
use Symfony\Component\Workflow\DependencyInjection\Configuration\ArcConfig;
use Symfony\Component\Workflow\DependencyInjection\Configuration\PlaceConfig;
use Symfony\Component\Workflow\DependencyInjection\Configuration\TransitionConfig;
use Symfony\Component\Workflow\DependencyInjection\Configuration\WorkflowConfig;
use Symfony\Component\Workflow\Validator\DefinitionValidatorInterface;
use Symfony\Component\Workflow\WeightedPlace;
use Symfony\Component\Workflow\WorkflowEvents;

/**
 * @author Antonio Pauletich <antonio.pauletich95@gmail.com>
 *
 * @internal
 */
final class WorkflowDefinitionPass implements CompilerPassInterface
{
    public const TAG = '.workflow.definition';

    public function process(ContainerBuilder $container): void
    {
        $taggedIds = array_keys($container->findTaggedResourceIds(self::TAG));

        if (!$taggedIds) {
            return;
        }

        $workflows = [];
        $declarations = [];
        foreach ($taggedIds as $id) {
            $class = $container->getDefinition($id)->getClass();
            $reflection = $class ? $container->getReflectionClass($class, false) : null;
            if (!$reflection) {
                throw new LogicException(\sprintf('Class "%s" declared as a workflow definition could not be loaded.', $class ?? $id));
            }
            if (!$reflection->isEnum()) {
                throw new LogicException(\sprintf('#[AsWorkflowDefinition] can only be used on an enum; "%s" is not an enum.', $class ?? $id));
            }

            if ($file = $reflection->getFileName()) {
                $container->addResource(new FileResource($file));
            }
            $enum = new \ReflectionEnum($reflection->getName());
            $workflow = $this->readWorkflow($enum);
            if (isset($declarations[$workflow->name])) {
                throw new LogicException(\sprintf('Workflow "%s" is declared by both enum "%s" and enum "%s". Workflow names must be unique.', $workflow->name, $declarations[$workflow->name], $enum->getName()));
            }

            $declarations[$workflow->name] = $enum->getName();
            $workflows[] = [$enum->getName(), $workflow];
        }

        if (!$container->hasDefinition('workflow.registry')) {
            [$enum, $workflow] = $workflows[0];

            throw new LogicException(\sprintf('The Workflow component is disabled, but enum "%s" declares workflow "%s".', $enum, $workflow->name));
        }

        $configuredWorkflows = [];
        foreach ($container->findTaggedServiceIds('workflow') as $attributes) {
            foreach ($attributes as $attribute) {
                if (isset($attribute['name'])) {
                    $configuredWorkflows[$attribute['name']] = true;
                }
            }
        }

        $registrar = new WorkflowServiceRegistrar();
        $generatedIds = [];
        foreach ($workflows as [$enum, $workflow]) {
            if (isset($configuredWorkflows[$workflow->name])) {
                throw new LogicException(\sprintf('Workflow "%s" is declared both by enum "%s" and configuration. Workflow names must be unique.', $workflow->name, $enum));
            }

            foreach ($registrar->getGeneratedServiceIds($workflow) as $serviceId) {
                if ($container->hasDefinition($serviceId) || $container->hasAlias($serviceId)) {
                    throw new LogicException(\sprintf('Cannot register workflow "%s" declared by enum "%s": service ID "%s" already exists.', $workflow->name, $enum, $serviceId));
                }
                if (isset($generatedIds[$serviceId])) {
                    [$conflictingEnum, $conflictingWorkflow] = $generatedIds[$serviceId];

                    throw new LogicException(\sprintf('Cannot register workflow "%s" declared by enum "%s": generated service ID "%s" conflicts with workflow "%s" declared by enum "%s".', $workflow->name, $enum, $serviceId, $conflictingWorkflow, $conflictingEnum));
                }

                $generatedIds[$serviceId] = [$enum, $workflow->name];
            }
        }

        foreach ($workflows as [, $workflow]) {
            $registrar->register($container, $workflow);
        }
    }

    private function readWorkflow(\ReflectionEnum $enum): WorkflowConfig
    {
        $attribute = $enum->getAttributes(AsWorkflowDefinition::class)[0]->newInstance();
        $enumName = $enum->getName();

        if ('' === $attribute->name) {
            throw new LogicException(\sprintf('Workflow name cannot be empty on enum "%s".', $enumName));
        }
        if (($backingType = $enum->getBackingType()) instanceof \ReflectionNamedType && 'int' === $backingType->getName()) {
            throw new LogicException(\sprintf('Integer-backed workflow definition enums are not supported; use a unit or string-backed enum for "%s".', $enumName));
        }
        if ($attribute->supports && null !== $attribute->supportStrategy) {
            throw new LogicException(\sprintf('"supports" and "supportStrategy" cannot be used together on workflow definition enum "%s".', $enumName));
        }
        if (!$attribute->supports && null === $attribute->supportStrategy) {
            throw new LogicException(\sprintf('"supports" or "supportStrategy" must be configured on workflow definition enum "%s".', $enumName));
        }
        if (null !== $attribute->markingStoreProperty && null !== $attribute->markingStoreService) {
            throw new LogicException(\sprintf('"markingStoreProperty" and "markingStoreService" cannot be used together on workflow definition enum "%s".', $enumName));
        }

        foreach ($attribute->supports as $supportedClass) {
            if (!\is_string($supportedClass) || '' === $supportedClass || !class_exists($supportedClass) && !interface_exists($supportedClass, false)) {
                throw new LogicException(\sprintf('The supported class or interface "%s" declared by workflow definition enum "%s" does not exist.', \is_string($supportedClass) ? $supportedClass : get_debug_type($supportedClass), $enumName));
            }
        }
        if (null !== $attribute->supportStrategy && '' === $attribute->supportStrategy) {
            throw new LogicException(\sprintf('"supportStrategy" cannot be empty on workflow definition enum "%s".', $enumName));
        }
        if ((null !== $attribute->markingStoreProperty && '' === $attribute->markingStoreProperty) || (null !== $attribute->markingStoreService && '' === $attribute->markingStoreService)) {
            throw new LogicException(\sprintf('Marking store options cannot be empty on workflow definition enum "%s".', $enumName));
        }

        $this->validateEvents($attribute->eventsToDispatch, $enumName);
        $this->validateDefinitionValidators($attribute->definitionValidators, $enumName);

        foreach ($enum->getReflectionConstants() as $constant) {
            if ($constant->isEnumCase()) {
                continue;
            }
            if ($constant->getAttributes(Place::class) || $constant->getAttributes(Transition::class)) {
                throw new LogicException(\sprintf('Workflow attributes on enum constants are not supported; "%s::%s" must be an enum case.', $enumName, $constant->getName()));
            }
        }

        $places = [];
        $placeNames = [];
        foreach ($enum->getCases() as $case) {
            $value = $case->getValue();
            $name = $this->normalizeCase($value, $enum, 'Place');
            if ('' === $name) {
                throw new LogicException(\sprintf('Place name cannot be empty on workflow definition enum "%s".', $enumName));
            }
            if (isset($placeNames[$name])) {
                throw new LogicException(\sprintf('Workflow definition enum "%s" declares duplicate normalized place "%s".', $enumName, $name));
            }

            $placeNames[$name] = true;
            $metadata = $case->getAttributes(Place::class)[0] ?? null;
            $places[] = new PlaceConfig($name, $metadata?->newInstance()->metadata ?? []);
        }

        if (!$places) {
            throw new LogicException(\sprintf('Workflow definition enum "%s" must declare at least one case.', $enumName));
        }

        $transitions = [];
        foreach ($enum->getAttributes(Transition::class) as $transitionAttribute) {
            $transition = $this->instantiateTransition($transitionAttribute, $enum);
            if (null === $from = $transition->from) {
                throw new LogicException(\sprintf('Transition "%s" on enum "%s" must declare at least one "from" place.', $transition->name, $enumName));
            }

            $transitions[] = $this->normalizeTransition($transition, $from, $enum, $placeNames);
        }

        foreach ($enum->getCases() as $case) {
            foreach ($case->getAttributes(Transition::class) as $transitionAttribute) {
                $transition = $this->instantiateTransition($transitionAttribute, $enum);
                if (null !== $transition->from) {
                    throw new LogicException(\sprintf('Transition "%s" on case "%s::%s" must not declare "from"; the case is the inferred source place.', $transition->name, $enumName, $case->getName()));
                }

                $transitions[] = $this->normalizeTransition($transition, $case->getValue(), $enum, $placeNames);
            }
        }

        if (!$transitions) {
            throw new LogicException(\sprintf('Workflow definition enum "%s" must declare at least one transition.', $enumName));
        }

        $initialMarking = [];
        if (null !== $attribute->initialMarking) {
            $initialMarking = \is_array($attribute->initialMarking) ? $attribute->initialMarking : [$attribute->initialMarking];
            $initialMarking = array_map(fn ($case): string => $this->normalizeCase($case, $enum, 'Initial marking'), $initialMarking);
        }

        return new WorkflowConfig(
            $attribute->name,
            $attribute->type,
            $places,
            $transitions,
            $initialMarking,
            $attribute->supports,
            $attribute->supportStrategy,
            $attribute->metadata,
            $attribute->markingStoreProperty,
            $attribute->markingStoreService,
            $attribute->auditTrail,
            $attribute->eventsToDispatch,
            $attribute->definitionValidators,
        );
    }

    /**
     * @param \ReflectionAttribute<Transition> $attribute
     */
    private function instantiateTransition(\ReflectionAttribute $attribute, \ReflectionEnum $enum): Transition
    {
        $arguments = $attribute->getArguments();
        $name = $arguments['name'] ?? $arguments[0] ?? null;
        if (!\is_string($name)) {
            throw new LogicException(\sprintf('Transition name on workflow definition enum "%s" must be a string; "%s" given.', $enum->getName(), get_debug_type($name)));
        }

        return $attribute->newInstance();
    }

    /**
     * @param \UnitEnum|WeightedPlace|list<\UnitEnum|WeightedPlace> $from
     * @param array<string, true>                                   $places
     */
    private function normalizeTransition(Transition $transition, \UnitEnum|WeightedPlace|array $from, \ReflectionEnum $enum, array $places): TransitionConfig
    {
        if ('' === $transition->name) {
            throw new LogicException(\sprintf('Transition name cannot be empty on workflow definition enum "%s".', $enum->getName()));
        }
        if (null !== $transition->guard && '' === $transition->guard) {
            throw new LogicException(\sprintf('Guard expression for transition "%s" cannot be empty on workflow definition enum "%s".', $transition->name, $enum->getName()));
        }

        return new TransitionConfig(
            $transition->name,
            $this->normalizeArcs($from, $enum, $places, $transition->name, 'from'),
            $this->normalizeArcs($transition->to, $enum, $places, $transition->name, 'to'),
            $transition->guard,
            $transition->metadata,
        );
    }

    /**
     * @param \UnitEnum|WeightedPlace|list<\UnitEnum|WeightedPlace>|null $values
     * @param array<string, true>                                        $places
     *
     * @return list<ArcConfig>
     */
    private function normalizeArcs(\UnitEnum|WeightedPlace|array|null $values, \ReflectionEnum $enum, array $places, string $transition, string $direction): array
    {
        $values = null === $values ? [] : (\is_array($values) ? $values : [$values]);
        if (!$values) {
            throw new LogicException(\sprintf('Transition "%s" on enum "%s" must declare at least one "%s" place.', $transition, $enum->getName(), $direction));
        }

        $arcs = [];
        foreach ($values as $value) {
            if ($value instanceof WeightedPlace) {
                $place = $this->normalizeCase($value->place, $enum, \sprintf('Transition "%s"', $transition));
                $weight = $value->weight;
            } elseif ($value instanceof \UnitEnum) {
                $place = $this->normalizeCase($value, $enum, \sprintf('Transition "%s"', $transition));
                $weight = 1;
            } else {
                throw new LogicException(\sprintf('Transition "%s" on enum "%s" has an invalid "%s" arc of type "%s".', $transition, $enum->getName(), $direction, get_debug_type($value)));
            }

            if (!isset($places[$place])) {
                throw new LogicException(\sprintf('Transition "%s" references place "%s", which is not a case of "%s".', $transition, $place, $enum->getName()));
            }
            $arcs[] = new ArcConfig($place, $weight);
        }

        return $arcs;
    }

    private function normalizeCase(mixed $case, \ReflectionEnum $enum, string $context): string
    {
        if (!$case instanceof \UnitEnum || $case::class !== $enum->getName()) {
            $caseName = $case instanceof \UnitEnum ? $case::class.'::'.$case->name : get_debug_type($case);

            throw new LogicException($context.\sprintf(' references "%s", which is not a case of "%s".', $caseName, $enum->getName()));
        }

        if ($case instanceof \BackedEnum) {
            if (!\is_string($case->value)) {
                throw new LogicException($context.\sprintf(' references integer-backed case "%s::%s", which cannot be used as a workflow place.', $case::class, $case->name));
            }

            return $case->value;
        }

        return $case->name;
    }

    /**
     * @param list<string>|null $events
     */
    private function validateEvents(?array $events, string $enum): void
    {
        if (null === $events) {
            return;
        }

        $hasAllowList = false;
        $hasBlockList = false;
        foreach ($events as $event) {
            if (!\is_string($event)) {
                throw new LogicException(\sprintf('Events dispatched by workflow definition enum "%s" must be strings.', $enum));
            }
            $blocked = str_starts_with($event, '!');
            $name = $blocked ? substr($event, 1) : $event;
            if (!\in_array($name, WorkflowEvents::ALIASES, true)) {
                throw new LogicException(\sprintf('Unknown workflow event "%s" on workflow definition enum "%s".', $event, $enum));
            }
            if ($blocked && WorkflowEvents::GUARD === $name) {
                throw new LogicException(\sprintf('The "%s" event cannot be disabled on workflow definition enum "%s".', WorkflowEvents::GUARD, $enum));
            }
            $hasBlockList = $hasBlockList || $blocked;
            $hasAllowList = $hasAllowList || !$blocked;
        }

        if ($hasAllowList && $hasBlockList) {
            throw new LogicException(\sprintf('Cannot mix allow-list and block-list events on workflow definition enum "%s".', $enum));
        }
    }

    /**
     * @param list<class-string<DefinitionValidatorInterface>> $validators
     */
    private function validateDefinitionValidators(array $validators, string $enum): void
    {
        foreach ($validators as $validator) {
            if (!\is_string($validator) || !class_exists($validator)) {
                throw new LogicException(\sprintf('The validation class "%s" declared by workflow definition enum "%s" does not exist.', \is_string($validator) ? $validator : get_debug_type($validator), $enum));
            }
            if (!is_a($validator, DefinitionValidatorInterface::class, true)) {
                throw new LogicException(\sprintf('The validation class "%s" declared by workflow definition enum "%s" must implement "%s".', $validator, $enum, DefinitionValidatorInterface::class));
            }
            if (1 <= (new \ReflectionClass($validator))->getConstructor()?->getNumberOfRequiredParameters()) {
                throw new LogicException(\sprintf('The validation class "%s" declared by workflow definition enum "%s" must have a constructor without required arguments.', $validator, $enum));
            }
        }
    }
}
