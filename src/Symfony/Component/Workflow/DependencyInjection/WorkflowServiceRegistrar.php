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

use Symfony\Component\DependencyInjection\Attribute\Target;
use Symfony\Component\DependencyInjection\ChildDefinition;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Exception\InvalidArgumentException;
use Symfony\Component\DependencyInjection\Exception\LogicException;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\ExpressionLanguage\ExpressionLanguage;
use Symfony\Component\Security\Core\AuthenticationEvents;
use Symfony\Component\Workflow\Arc;
use Symfony\Component\Workflow\Definition as WorkflowDefinition;
use Symfony\Component\Workflow\DependencyInjection\Configuration\ArcConfig;
use Symfony\Component\Workflow\DependencyInjection\Configuration\PlaceConfig;
use Symfony\Component\Workflow\DependencyInjection\Configuration\TransitionConfig;
use Symfony\Component\Workflow\DependencyInjection\Configuration\WorkflowConfig;
use Symfony\Component\Workflow\EventListener\AuditTrailListener;
use Symfony\Component\Workflow\EventListener\GuardExpression;
use Symfony\Component\Workflow\EventListener\GuardListener;
use Symfony\Component\Workflow\Metadata\InMemoryMetadataStore;
use Symfony\Component\Workflow\SupportStrategy\InstanceOfSupportStrategy;
use Symfony\Component\Workflow\Transition;
use Symfony\Component\Workflow\Validator\StateMachineValidator;
use Symfony\Component\Workflow\Validator\WorkflowValidator;
use Symfony\Component\Workflow\WorkflowInterface;
use Symfony\Component\Workflow\WorkflowType;

/**
 * @author Antonio Pauletich <antonio.pauletich95@gmail.com>
 *
 * @internal
 */
final class WorkflowServiceRegistrar
{
    /**
     * @return list<string>
     */
    public function getGeneratedServiceIds(WorkflowConfig $workflow): array
    {
        $workflowId = \sprintf('%s.%s', $workflow->type->value, $workflow->name);
        $serviceIds = [
            $workflowId,
            \sprintf('%s.metadata_store', $workflowId),
            \sprintf('%s.definition', $workflowId),
        ];

        $transitionCounter = 0;
        foreach ($workflow->transitions as $transition) {
            $transitionCount = WorkflowType::Workflow === $workflow->type ? 1 : \count($transition->from) * \count($transition->to);
            while ($transitionCount--) {
                $serviceIds[] = \sprintf('.%s.transition.%s', $workflowId, $transitionCounter++);
            }
        }

        if ($workflow->auditTrail) {
            $serviceIds[] = \sprintf('.%s.listener.audit_trail', $workflowId);
        }
        foreach ($workflow->transitions as $transition) {
            if (null !== $transition->guard) {
                $serviceIds[] = \sprintf('.%s.listener.guard', $workflowId);

                break;
            }
        }

        array_push($serviceIds, ...$this->getAutowiringAliasIds($workflowId, $workflow));

        return $serviceIds;
    }

    public function register(ContainerBuilder $container, WorkflowConfig $workflow): void
    {
        $name = $workflow->name;
        $type = $workflow->type->value;
        $workflowId = \sprintf('%s.%s', $type, $name);
        // Registration only needs the validation side effect; WorkflowDefinitionPass uses
        // the returned IDs to detect collisions before registration.
        $this->getAutowiringAliasIds($workflowId, $workflow);

        $metadataStoreDefinition = new Definition(InMemoryMetadataStore::class, [[], [], null]);
        if ($workflow->metadata) {
            $metadataStoreDefinition->replaceArgument(0, $workflow->metadata);
        }
        $placesMetadata = [];
        foreach ($workflow->places as $place) {
            if ($place->metadata) {
                $placesMetadata[$place->name] = $place->metadata;
            }
        }
        if ($placesMetadata) {
            $metadataStoreDefinition->replaceArgument(1, $placesMetadata);
        }

        $transitions = [];
        $guardsConfiguration = [];
        $transitionsMetadataDefinition = new Definition(\SplObjectStorage::class);
        $transitionCounter = 0;
        foreach ($workflow->transitions as $transition) {
            $from = array_map(self::createArcDefinition(...), $transition->from);
            $to = array_map(self::createArcDefinition(...), $transition->to);

            if (WorkflowType::Workflow === $workflow->type) {
                $this->registerTransition($container, $workflowId, $name, $transition, $from, $to, $transitionCounter++, $transitions, $guardsConfiguration, $transitionsMetadataDefinition);
            } else {
                foreach ($from as $fromArc) {
                    foreach ($to as $toArc) {
                        $this->registerTransition($container, $workflowId, $name, $transition, [$fromArc], [$toArc], $transitionCounter++, $transitions, $guardsConfiguration, $transitionsMetadataDefinition);
                    }
                }
            }
        }
        $metadataStoreDefinition->replaceArgument(2, $transitionsMetadataDefinition);
        $metadataStoreId = \sprintf('%s.metadata_store', $workflowId);
        $container->setDefinition($metadataStoreId, $metadataStoreDefinition);

        $definitionDefinition = new Definition(WorkflowDefinition::class);
        $definitionDefinition->addArgument(array_map(static fn (PlaceConfig $place): string => $place->name, $workflow->places));
        $definitionDefinition->addArgument($transitions);
        $definitionDefinition->addArgument($workflow->initialMarking);
        $definitionDefinition->addArgument(new Reference($metadataStoreId));
        $definitionDefinitionId = \sprintf('%s.definition', $workflowId);

        $markingStoreDefinition = null;
        if (null !== $workflow->markingStoreProperty) {
            $markingStoreDefinition = new ChildDefinition('workflow.marking_store.method');
            $markingStoreDefinition->setArguments([
                WorkflowType::StateMachine === $workflow->type,
                $workflow->markingStoreProperty,
            ]);
        } elseif (null !== $workflow->markingStoreService) {
            $markingStoreDefinition = new Reference($workflow->markingStoreService);
        }

        $definitionValidators = $workflow->definitionValidators;
        $definitionValidators[] = match ($workflow->type) {
            WorkflowType::StateMachine => StateMachineValidator::class,
            WorkflowType::Workflow => WorkflowValidator::class,
        };

        $workflowDefinition = new ChildDefinition(\sprintf('%s.abstract', $type));
        $workflowDefinition->replaceArgument(0, new Reference($definitionDefinitionId));
        $workflowDefinition->replaceArgument(1, $markingStoreDefinition);
        $workflowDefinition->replaceArgument(3, $name);
        $workflowDefinition->replaceArgument(4, $workflow->eventsToDispatch);

        $workflowDefinition->addTag('workflow', [
            'name' => $name,
            'metadata' => $workflow->metadata,
            'definition_validators' => $definitionValidators,
            'definition_id' => $definitionDefinitionId,
        ]);
        $workflowDefinition->addTag(WorkflowType::Workflow === $workflow->type ? 'workflow.workflow' : 'workflow.state_machine', ['name' => $name]);

        $container->setDefinition($workflowId, $workflowDefinition);
        $container->setDefinition($definitionDefinitionId, $definitionDefinition);
        $container->registerAliasForArgument($workflowId, WorkflowInterface::class, $name.'.'.$type, $name);

        $registryDefinition = $container->getDefinition('workflow.registry');
        if ($workflow->supports) {
            foreach ($workflow->supports as $supportedClassName) {
                $strategyDefinition = new Definition(InstanceOfSupportStrategy::class, [$supportedClassName]);
                $registryDefinition->addMethodCall('addWorkflow', [new Reference($workflowId), $strategyDefinition]);
            }
        } elseif (null !== $workflow->supportStrategy) {
            $registryDefinition->addMethodCall('addWorkflow', [new Reference($workflowId), new Reference($workflow->supportStrategy)]);
        }

        if ($workflow->auditTrail) {
            $listener = new Definition(AuditTrailListener::class);
            $listener->addTag('monolog.logger', ['channel' => 'workflow']);
            $listener->addTag('kernel.event_listener', ['event' => \sprintf('workflow.%s.leave', $name), 'method' => 'onLeave']);
            $listener->addTag('kernel.event_listener', ['event' => \sprintf('workflow.%s.transition', $name), 'method' => 'onTransition']);
            $listener->addTag('kernel.event_listener', ['event' => \sprintf('workflow.%s.enter', $name), 'method' => 'onEnter']);
            $listener->addArgument(new Reference('logger'));
            $container->setDefinition(\sprintf('.%s.listener.audit_trail', $workflowId), $listener);
        }

        if ($guardsConfiguration) {
            if (!class_exists(ExpressionLanguage::class)) {
                throw new LogicException('Cannot guard workflows as the ExpressionLanguage component is not installed. Try running "composer require symfony/expression-language".');
            }

            if (!class_exists(AuthenticationEvents::class)) {
                throw new LogicException('Cannot guard workflows as the Security component is not installed. Try running "composer require symfony/security-core".');
            }

            $guard = new Definition(GuardListener::class);
            $guard->setArguments([
                $guardsConfiguration,
                new Reference('workflow.security.expression_language'),
                new Reference('security.token_storage'),
                new Reference('security.authorization_checker'),
                new Reference('security.authentication.trust_resolver'),
                new Reference('security.role_hierarchy'),
                new Reference('validator', ContainerInterface::NULL_ON_INVALID_REFERENCE),
            ]);
            foreach (array_keys($guardsConfiguration) as $eventName) {
                $guard->addTag('kernel.event_listener', ['event' => $eventName, 'method' => 'onTransition']);
            }

            $container->setDefinition(\sprintf('.%s.listener.guard', $workflowId), $guard);
            $container->setParameter('workflow.has_guard_listeners', true);
        }
    }

    private static function createArcDefinition(ArcConfig $arc): Definition
    {
        return new Definition(Arc::class, [$arc->place, $arc->weight]);
    }

    /**
     * @return list<string>
     */
    private function getAutowiringAliasIds(string $workflowId, WorkflowConfig $workflow): array
    {
        $aliasName = $workflow->name.'.'.$workflow->type->value;
        $parsedAliasName = (new Target($aliasName))->getParsedName();
        if (!preg_match('/^[a-zA-Z_\x7f-\xff]/', $parsedAliasName)) {
            $service = $workflowId === $aliasName ? '' : \sprintf(' for service "%s"', $workflowId);

            throw new InvalidArgumentException(\sprintf('Invalid argument name "%s"%s: the first character must be a letter.', $aliasName, $service));
        }

        $serviceIds = [];
        if ($parsedAliasName !== $workflow->name) {
            $serviceIds[] = '.'.WorkflowInterface::class.' $'.$workflow->name;
        }
        $serviceIds[] = WorkflowInterface::class.' $'.$parsedAliasName;

        return $serviceIds;
    }

    /**
     * @param list<Definition>                $from
     * @param list<Definition>                $to
     * @param list<Reference>                 $transitions
     * @param array<string, list<Definition>> $guardsConfiguration
     */
    private function registerTransition(ContainerBuilder $container, string $workflowId, string $workflowName, TransitionConfig $transition, array $from, array $to, int $counter, array &$transitions, array &$guardsConfiguration, Definition $transitionsMetadataDefinition): void
    {
        $transitionId = \sprintf('.%s.transition.%s', $workflowId, $counter);
        $container->register($transitionId, Transition::class)
            ->setArguments([$transition->name, $from, $to]);
        $transitions[] = new Reference($transitionId);

        if (null !== $transition->guard) {
            $eventName = \sprintf('workflow.%s.guard.%s', $workflowName, $transition->name);
            $guardsConfiguration[$eventName][] = new Definition(GuardExpression::class, [new Reference($transitionId), $transition->guard]);
        }
        if ($transition->metadata) {
            $transitionsMetadataDefinition->addMethodCall('offsetSet', [new Reference($transitionId), $transition->metadata]);
        }
    }
}
