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
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
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
use Symfony\Component\Workflow\WorkflowTrait;

/**
 * @author Grégoire Pineau <lyrixx@lyrixx.info>
 */
class WorkflowServiceCreatorPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container): void
    {
        if (!$container->hasDefinition('workflow.registry')) {
            return;
        }

        $registryDefinition = $container->getDefinition('workflow.registry');

        if ($container->hasParameter('.workflow.workflows')) {
            $workflows = $container->getParameter('.workflow.workflows');
            foreach ($workflows as $name => $workflow) {
                $this->createWorkflow($container, $registryDefinition, $name, $workflow);
            }
        }

        foreach ($container->findTaggedServiceIds('.workflow.attribute', true) as $id => $attributes) {
            if (!$workflow = $attributes[0]['configuration'] ?? null) {
                throw new LogicException(\sprintf('The service "%s" must define the "configuration" attribute on its "%s" tag.', $id, '.workflow.attribute'));
            }
            $workflowId = $this->createWorkflow($container, $registryDefinition, $workflow['name'], $workflow);
            $definition = $container->getDefinition($id);
            $definition->clearTag('.workflow.attribute');
            $reflection = $container->getReflectionClass($definition->getClass());
            if (\in_array(WorkflowTrait::class, $reflection->getTraitNames(), true)) {
                $definition->addMethodCall('setWorkflow', [new Reference($workflowId)]);
            }
        }
    }

    private function createWorkflow(ContainerBuilder $container, Definition $registryDefinition, string $name, array $workflow): string
    {
        $type = $workflow['type'];
        $workflowId = \sprintf('%s.%s', $type, $name);

        // Process Metadata (workflow + places (transition is done in the "create transition" block))
        $metadataStoreDefinition = new Definition(InMemoryMetadataStore::class, [[], [], null]);
        if ($workflow['metadata']) {
            $metadataStoreDefinition->replaceArgument(0, $workflow['metadata']);
        }
        $placesMetadata = [];
        foreach ($workflow['places'] as $place) {
            if ($place['metadata']) {
                $placesMetadata[$place['name']] = $place['metadata'];
            }
        }
        if ($placesMetadata) {
            $metadataStoreDefinition->replaceArgument(1, $placesMetadata);
        }

        // Create transitions
        $transitions = [];
        $guardsConfiguration = [];
        $transitionsMetadataDefinition = new Definition(\SplObjectStorage::class);
        // Global transition counter per workflow
        $transitionCounter = 0;
        foreach ($workflow['transitions'] as $transition) {
            foreach (['from', 'to'] as $direction) {
                foreach ($transition[$direction] as $k => $arc) {
                    $transition[$direction][$k] = new Definition(Arc::class, [$arc['place'], $arc['weight'] ?? 1]);
                }
            }
            if ('workflow' === $type) {
                $transitionId = \sprintf('.%s.transition.%s', $workflowId, $transitionCounter++);
                $container->register($transitionId, Transition::class)
                    ->setArguments([$transition['name'], $transition['from'], $transition['to']]);
                $transitions[] = new Reference($transitionId);
                if (isset($transition['guard'])) {
                    $eventName = \sprintf('workflow.%s.guard.%s', $name, $transition['name']);
                    $guardsConfiguration[$eventName][] = new Definition(
                        GuardExpression::class,
                        [new Reference($transitionId), $transition['guard']]
                    );
                }
                if ($transition['metadata']) {
                    $transitionsMetadataDefinition->addMethodCall('offsetSet', [
                        new Reference($transitionId),
                        $transition['metadata'],
                    ]);
                }
            } elseif ('state_machine' === $type) {
                foreach ($transition['from'] as $from) {
                    foreach ($transition['to'] as $to) {
                        $transitionId = \sprintf('.%s.transition.%s', $workflowId, $transitionCounter++);
                        $container->register($transitionId, Transition::class)
                            ->setArguments([$transition['name'], [$from], [$to]]);
                        $transitions[] = new Reference($transitionId);
                        if (isset($transition['guard'])) {
                            $eventName = \sprintf('workflow.%s.guard.%s', $name, $transition['name']);
                            $guardsConfiguration[$eventName][] = new Definition(
                                GuardExpression::class,
                                [new Reference($transitionId), $transition['guard']]
                            );
                        }
                        if ($transition['metadata']) {
                            $transitionsMetadataDefinition->addMethodCall('offsetSet', [
                                new Reference($transitionId),
                                $transition['metadata'],
                            ]);
                        }
                    }
                }
            }
        }
        $metadataStoreDefinition->replaceArgument(2, $transitionsMetadataDefinition);
        $metadataStoreId = \sprintf('%s.metadata_store', $workflowId);
        $container->setDefinition($metadataStoreId, $metadataStoreDefinition);

        // Create places
        $places = array_column($workflow['places'], 'name');
        $initialMarking = $workflow['initial_marking'] ?? [];

        // Create a Definition
        $definitionDefinition = new Definition(WorkflowDefinition::class);
        $definitionDefinition->addArgument($places);
        $definitionDefinition->addArgument($transitions);
        $definitionDefinition->addArgument($initialMarking);
        $definitionDefinition->addArgument(new Reference($metadataStoreId));
        $definitionDefinitionId = \sprintf('%s.definition', $workflowId);

        // Create MarkingStore
        $markingStoreDefinition = null;
        if (isset($workflow['marking_store']['type']) || isset($workflow['marking_store']['property'])) {
            $markingStoreDefinition = new ChildDefinition('workflow.marking_store.method');
            $markingStoreDefinition->setArguments([
                'state_machine' === $type, // single state
                $workflow['marking_store']['property'] ?? 'marking',
            ]);
        } elseif (isset($workflow['marking_store']['service'])) {
            $markingStoreDefinition = new Reference($workflow['marking_store']['service']);
        }

        // Validation
        $workflow['definition_validators'][] = match ($workflow['type']) {
            'state_machine' => StateMachineValidator::class,
            'workflow' => WorkflowValidator::class,
            default => throw new \LogicException(\sprintf('Invalid workflow type "%s".', $workflow['type'])),
        };

        // Create Workflow
        $workflowDefinition = new ChildDefinition(\sprintf('%s.abstract', $type));
        $workflowDefinition->replaceArgument(0, new Reference($definitionDefinitionId));
        $workflowDefinition->replaceArgument(1, $markingStoreDefinition);
        $workflowDefinition->replaceArgument(3, $name);
        $workflowDefinition->replaceArgument(4, $workflow['events_to_dispatch']);

        $workflowDefinition->addTag('workflow', [
            'name' => $name,
            'metadata' => $workflow['metadata'],
            'definition_validators' => $workflow['definition_validators'],
            'definition_id' => $definitionDefinitionId,
        ]);
        if ('workflow' === $type) {
            $workflowDefinition->addTag('workflow.workflow', ['name' => $name]);
        } elseif ('state_machine' === $type) {
            $workflowDefinition->addTag('workflow.state_machine', ['name' => $name]);
        }

        // Store to container
        $container->setDefinition($workflowId, $workflowDefinition);
        $container->setDefinition($definitionDefinitionId, $definitionDefinition);
        $container->registerAliasForArgument($workflowId, WorkflowInterface::class, $name.'.'.$type, $name);

        // Add workflow to Registry
        if ($workflow['supports']) {
            foreach ($workflow['supports'] as $supportedClassName) {
                $strategyDefinition = new Definition(InstanceOfSupportStrategy::class, [$supportedClassName]);
                $registryDefinition->addMethodCall('addWorkflow', [new Reference($workflowId), $strategyDefinition]);
            }
        } elseif (isset($workflow['support_strategy'])) {
            $registryDefinition->addMethodCall('addWorkflow', [new Reference($workflowId), new Reference($workflow['support_strategy'])]);
        }

        // Enable the AuditTrail
        if ($workflow['audit_trail']['enabled']) {
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
            foreach ($guardsConfiguration as $eventName => $config) {
                $guard->addTag('kernel.event_listener', ['event' => $eventName, 'method' => 'onTransition']);
            }

            $container->setDefinition(\sprintf('.%s.listener.guard', $workflowId), $guard);
            $container->setParameter('workflow.has_guard_listeners', true);
        }

        return $workflowId;
    }
}
