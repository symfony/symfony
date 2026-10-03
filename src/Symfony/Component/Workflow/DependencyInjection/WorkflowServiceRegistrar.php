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

use Symfony\Component\DependencyInjection\ChildDefinition;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Exception\LogicException;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\ExpressionLanguage\ExpressionLanguage;
use Symfony\Component\Security\Core\AuthenticationEvents;
use Symfony\Component\Workflow\Arc;
use Symfony\Component\Workflow\Definition as WorkflowDefinition;
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
 * Registers the services of a workflow in the container.
 *
 * @author Grégoire Pineau <lyrixx@lyrixx.info>
 *
 * @internal
 */
final class WorkflowServiceRegistrar
{
    /**
     * @return string The id of the workflow service
     */
    public function register(ContainerBuilder $container, WorkflowDescriptor $workflow): string
    {
        $name = $workflow->name;
        $type = $workflow->type->value;
        $workflowId = \sprintf('%s.%s', $type, $name);

        // Process Metadata (workflow + places (transition is done in the "create transition" block))
        $metadataStoreDefinition = new Definition(InMemoryMetadataStore::class, [[], [], null]);
        if ($workflow->metadata) {
            $metadataStoreDefinition->replaceArgument(0, $workflow->metadata);
        }
        if ($placesMetadata = array_filter($workflow->places)) {
            $metadataStoreDefinition->replaceArgument(1, $placesMetadata);
        }

        // Create transitions
        $transitions = [];
        $guardsConfiguration = [];
        $transitionsMetadataDefinition = new Definition(\SplObjectStorage::class);
        // Global transition counter per workflow
        $transitionCounter = 0;
        foreach ($workflow->transitions as $transition) {
            $from = array_map(self::createArcDefinition(...), $transition->from);
            $to = array_map(self::createArcDefinition(...), $transition->to);

            if (WorkflowType::Workflow === $workflow->type) {
                $arcs = [[$from, $to]];
            } else {
                // A transition of a state machine has exactly one input and one output
                $arcs = [];
                foreach ($from as $fromArc) {
                    foreach ($to as $toArc) {
                        $arcs[] = [[$fromArc], [$toArc]];
                    }
                }
            }

            foreach ($arcs as [$from, $to]) {
                $transitionId = \sprintf('.%s.transition.%s', $workflowId, $transitionCounter++);
                $container->register($transitionId, Transition::class)
                    ->setArguments([$transition->name, $from, $to]);
                $transitions[] = new Reference($transitionId);
                if (null !== $transition->guard) {
                    $eventName = \sprintf('workflow.%s.guard.%s', $name, $transition->name);
                    $guardsConfiguration[$eventName][] = new Definition(GuardExpression::class, [new Reference($transitionId), $transition->guard]);
                }
                if ($transition->metadata) {
                    $transitionsMetadataDefinition->addMethodCall('offsetSet', [new Reference($transitionId), $transition->metadata]);
                }
            }
        }
        $metadataStoreDefinition->replaceArgument(2, $transitionsMetadataDefinition);
        $metadataStoreId = \sprintf('%s.metadata_store', $workflowId);
        $container->setDefinition($metadataStoreId, $metadataStoreDefinition);

        // Create a Definition
        $definitionDefinition = new Definition(WorkflowDefinition::class);
        $definitionDefinition->addArgument(array_map(strval(...), array_keys($workflow->places)));
        $definitionDefinition->addArgument($transitions);
        $definitionDefinition->addArgument($workflow->initialMarking);
        $definitionDefinition->addArgument(new Reference($metadataStoreId));
        $definitionDefinitionId = \sprintf('%s.definition', $workflowId);

        // Create MarkingStore
        $markingStoreDefinition = null;
        if (null !== $workflow->markingProperty) {
            $markingStoreDefinition = new ChildDefinition('workflow.marking_store.method');
            $markingStoreDefinition->setArguments([
                WorkflowType::StateMachine === $workflow->type, // single state
                $workflow->markingProperty,
            ]);
        } elseif (null !== $workflow->markingStore) {
            $markingStoreDefinition = new Reference($workflow->markingStore);
        }

        // Validation
        $definitionValidators = $workflow->definitionValidators;
        $definitionValidators[] = match ($workflow->type) {
            WorkflowType::StateMachine => StateMachineValidator::class,
            WorkflowType::Workflow => WorkflowValidator::class,
        };

        // Create Workflow
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
        $workflowDefinition->addTag(\sprintf('workflow.%s', $type), ['name' => $name]);

        // Store to container
        $container->setDefinition($workflowId, $workflowDefinition);
        $container->setDefinition($definitionDefinitionId, $definitionDefinition);
        $container->registerAliasForArgument($workflowId, WorkflowInterface::class, $name.'.'.$type, $name);

        // Add workflow to Registry
        $registryDefinition = $container->getDefinition('workflow.registry');
        foreach ($workflow->supports as $supportedClassName) {
            $strategyDefinition = new Definition(InstanceOfSupportStrategy::class, [$supportedClassName]);
            $registryDefinition->addMethodCall('addWorkflow', [new Reference($workflowId), $strategyDefinition]);
        }
        if (null !== $workflow->supportStrategy) {
            $registryDefinition->addMethodCall('addWorkflow', [new Reference($workflowId), new Reference($workflow->supportStrategy)]);
        }

        // Enable the AuditTrail
        if ($workflow->auditTrail) {
            $listener = new Definition(AuditTrailListener::class);
            $listener->addTag('monolog.logger', ['channel' => 'workflow']);
            $listener->addTag('kernel.event_listener', ['event' => \sprintf('workflow.%s.leave', $name), 'method' => 'onLeave']);
            $listener->addTag('kernel.event_listener', ['event' => \sprintf('workflow.%s.transition', $name), 'method' => 'onTransition']);
            $listener->addTag('kernel.event_listener', ['event' => \sprintf('workflow.%s.enter', $name), 'method' => 'onEnter']);
            $listener->addArgument(new Reference('logger'));
            $container->setDefinition(\sprintf('.%s.listener.audit_trail', $workflowId), $listener);
        }

        // Add Guard Listener
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

        return $workflowId;
    }

    private static function createArcDefinition(Arc $arc): Definition
    {
        return new Definition(Arc::class, [$arc->place, $arc->weight]);
    }
}
