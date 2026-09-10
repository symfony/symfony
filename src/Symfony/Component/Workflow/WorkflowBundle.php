<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Workflow;

use Symfony\Component\Config\Definition\Configurator\DefinitionConfigurator;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\Config\Resource\ClassExistenceResource;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\ConsoleBundle;
use Symfony\Component\DependencyInjection\ChildDefinition;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Exception\LogicException;
use Symfony\Component\DependencyInjection\Kernel\AbstractBundle;
use Symfony\Component\DependencyInjection\Kernel\RequiredBundle;
use Symfony\Component\DependencyInjection\Kernel\ServicesBundle;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\EventDispatcher\DependencyInjection\AddEventAliasesPass;
use Symfony\Component\ExpressionLanguage\ExpressionLanguage;
use Symfony\Component\Finder\Glob;
use Symfony\Component\Security\Core\AuthenticationEvents;
use Symfony\Component\Workflow\Definition as WorkflowDefinition;
use Symfony\Component\Workflow\DependencyInjection\WorkflowDebugPass;
use Symfony\Component\Workflow\DependencyInjection\WorkflowGuardListenerPass;
use Symfony\Component\Workflow\DependencyInjection\WorkflowValidatorPass;
use Symfony\Component\Workflow\EventListener\AuditTrailListener;
use Symfony\Component\Workflow\EventListener\GuardExpression;
use Symfony\Component\Workflow\EventListener\GuardListener;
use Symfony\Component\Workflow\Metadata\InMemoryMetadataStore;
use Symfony\Component\Workflow\SupportStrategy\InstanceOfSupportStrategy;
use Symfony\Component\Workflow\Validator\DefinitionValidatorInterface;
use Symfony\Component\Workflow\Validator\StateMachineValidator;
use Symfony\Component\Workflow\Validator\WorkflowValidator;

/**
 * Provides the workflow and state machine services.
 */
#[RequiredBundle(ServicesBundle::class)]
#[RequiredBundle(ConsoleBundle::class, ignoreOnInvalid: true)]
class WorkflowBundle extends AbstractBundle
{
    public function getPath(): string
    {
        return $this->path ??= __DIR__;
    }

    public function build(ContainerBuilder $container): void
    {
        $container->addResource(new ClassExistenceResource(AddEventAliasesPass::class));

        if (class_exists(AddEventAliasesPass::class)) {
            $container->addCompilerPass(new AddEventAliasesPass(WorkflowEvents::ALIASES));
        }

        $container->addCompilerPass(new WorkflowGuardListenerPass());
        $container->addCompilerPass(new WorkflowValidatorPass());

        if ($container->getParameter('kernel.debug')) {
            $container->addCompilerPass(new WorkflowDebugPass());
        }
    }

    public function configure(DefinitionConfigurator $definition): void
    {
        $definition->rootNode()
            ->canBeDisabled()
            ->beforeNormalization()
                ->ifArray()
                ->then(static function ($v) {
                    $v['enabled'] ??= true;

                    if (true === $v['enabled']) {
                        $workflows = $v;
                        unset($workflows['enabled']);

                        if (1 === \count($workflows) && isset($workflows[0]['enabled']) && 1 === \count($workflows[0])) {
                            $workflows = [];
                        }

                        if (1 === \count($workflows) && isset($workflows['workflows']) && !array_is_list($workflows['workflows']) && array_diff_key($workflows['workflows'], ['audit_trail' => 1, 'type' => 1, 'marking_store' => 1, 'supports' => 1, 'support_strategy' => 1, 'initial_marking' => 1, 'places' => 1, 'transitions' => 1])) {
                            $workflows = $workflows['workflows'];
                        }

                        foreach ($workflows as $key => $workflow) {
                            if (isset($workflow['enabled']) && false === $workflow['enabled']) {
                                throw new LogicException(\sprintf('Cannot disable a single workflow. Remove the configuration for the workflow "%s" instead.', $key));
                            }

                            unset($workflows[$key]['enabled']);
                        }

                        $v = [
                            'enabled' => true,
                            'workflows' => $workflows,
                        ];
                    }

                    return $v;
                })
            ->end()
            ->children()
                ->arrayNode('workflows', 'workflow')
                    ->useAttributeAsKey('name')
                    ->prototype('array')
                        ->children()
                            ->arrayNode('audit_trail')
                                ->canBeEnabled()
                            ->end()
                            ->enumNode('type')
                                ->values(['workflow', 'state_machine'])
                                ->defaultValue('state_machine')
                            ->end()
                            ->arrayNode('marking_store')
                                ->children()
                                    ->enumNode('type')
                                        ->values(['method'])
                                    ->end()
                                    ->scalarNode('property')
                                        ->cannotBeEmpty()
                                    ->end()
                                    ->scalarNode('service')
                                        ->cannotBeEmpty()
                                    ->end()
                                ->end()
                            ->end()
                            ->arrayNode('supports', 'support')
                                ->acceptAndWrap(['string'])
                                ->prototype('scalar')
                                    ->cannotBeEmpty()
                                    ->validate()
                                        ->ifTrue(static fn ($v) => !class_exists($v) && !interface_exists($v, false))
                                        ->thenInvalid('The supported class or interface "%s" does not exist.')
                                    ->end()
                                ->end()
                            ->end()
                            ->arrayNode('definition_validators', 'definition_validator')
                                ->prototype('scalar')
                                    ->cannotBeEmpty()
                                    ->validate()
                                        ->ifTrue(static fn ($v) => !class_exists($v))
                                        ->thenInvalid('The validation class %s does not exist.')
                                    ->end()
                                    ->validate()
                                        ->ifTrue(static fn ($v) => !is_a($v, DefinitionValidatorInterface::class, true))
                                        ->thenInvalid(\sprintf('The validation class %%s is not an instance of "%s".', DefinitionValidatorInterface::class))
                                    ->end()
                                    ->validate()
                                        ->ifTrue(static fn ($v) => 1 <= (new \ReflectionClass($v))->getConstructor()?->getNumberOfRequiredParameters())
                                        ->thenInvalid('The %s validation class constructor must not have any arguments.')
                                    ->end()
                                ->end()
                            ->end()
                            ->scalarNode('support_strategy')
                                ->cannotBeEmpty()
                            ->end()
                            ->arrayNode('initial_marking')
                                ->acceptAndWrap(['backed-enum', 'string'])
                                ->defaultValue([])
                                ->beforeNormalization()
                                    ->ifArray()
                                    ->then(static function ($markings) {
                                        $normalizedMarkings = [];
                                        foreach ($markings as $marking) {
                                            $normalizedMarkings[] = $marking instanceof \BackedEnum ? $marking->value : $marking;
                                        }

                                        return $normalizedMarkings;
                                    })
                                ->end()
                                ->prototype('scalar')->end()
                            ->end()
                            ->arrayNode('events_to_dispatch', 'event_to_dispatch')
                                ->defaultNull()
                                ->stringPrototype()->end()
                                ->validate()
                                    ->ifTrue(static function ($v) {
                                        foreach ($v as $value) {
                                            $name = str_starts_with($value, '!') ? substr($value, 1) : $value;
                                            if (!\in_array($name, WorkflowEvents::ALIASES, true)) {
                                                return true;
                                            }
                                        }

                                        return false;
                                    })
                                    ->thenInvalid('The value must be "null" or an array of workflow events (like ["workflow.enter"]). Prefix an event with "!" to disable it (e.g. ["!workflow.announce"]).')
                                ->end()
                                ->validate()
                                    ->ifTrue(static function ($v) {
                                        if (!\is_array($v) || !$v) {
                                            return false;
                                        }
                                        $hasAllowList = false;
                                        $hasBlockList = false;
                                        foreach ($v as $value) {
                                            if (str_starts_with($value, '!')) {
                                                $hasBlockList = true;
                                            } else {
                                                $hasAllowList = true;
                                            }
                                        }

                                        return $hasAllowList && $hasBlockList;
                                    })
                                    ->thenInvalid('Cannot mix allow-list and block-list entries in "events_to_dispatch": every entry must start with "!" (block-list mode) or none of them must (allow-list mode).')
                                ->end()
                                ->validate()
                                    ->ifTrue(static fn ($v) => \is_array($v) && \in_array('!'.WorkflowEvents::GUARD, $v, true))
                                    ->thenInvalid('The "workflow.guard" event cannot be disabled in "events_to_dispatch": it is always dispatched.')
                                ->end()
                                ->info('Select which Transition events should be dispatched for this Workflow. Prefix an event with "!" to disable it (e.g. ["!workflow.announce"]); future events are dispatched by default in block-list mode.')
                                ->example(['workflow.enter', 'workflow.transition'])
                            ->end()
                            ->arrayNode('places', 'place')
                                ->beforeNormalization()
                                    ->ifString()
                                    ->then(static function ($places) {
                                        if (2 !== \count($places = explode('::', $places, 2))) {
                                            throw new InvalidConfigurationException('The "places" option must be a "FQCN::glob" pattern in workflow configuration.');
                                        }
                                        [$class, $pattern] = $places;
                                        if (!class_exists($class) && !interface_exists($class, false)) {
                                            throw new InvalidConfigurationException(\sprintf('The "places" option must be a "FQCN::glob" pattern in workflow configuration, but class "%s" is not found.', $class));
                                        }
                                        if (!class_exists(Glob::class)) {
                                            throw new InvalidConfigurationException('Using a "FQCN::glob" pattern for the "places" option requires the Finder component. Try running "composer require symfony/finder".');
                                        }

                                        $places = [];
                                        $regex = Glob::toRegex($pattern, false);

                                        foreach ((new \ReflectionClass($class))->getConstants() as $name => $value) {
                                            if (preg_match($regex, $name)) {
                                                $places[] = $value;
                                            }
                                        }

                                        return $places ?: throw new InvalidConfigurationException(\sprintf('No places found for pattern "%s::%s" in workflow configuration.', $class, $pattern));
                                    })
                                ->end()
                                ->beforeNormalization()
                                    ->ifArray()
                                    ->then(static function ($places) {
                                        $normalizedPlaces = [];
                                        foreach ($places as $key => $value) {
                                            if ($value instanceof \BackedEnum) {
                                                $value = ['name' => $value->value];
                                            } elseif (!\is_array($value)) {
                                                $value = ['name' => $value];
                                            }
                                            $value['name'] ??= $key;
                                            $normalizedPlaces[] = $value;
                                        }

                                        return $normalizedPlaces;
                                    })
                                ->end()
                                ->prototype('array')
                                    ->children()
                                        ->scalarNode('name')
                                            ->isRequired()
                                            ->cannotBeEmpty()
                                        ->end()
                                        ->arrayNode('metadata')
                                            ->useAttributeAsKey('key')
                                            ->normalizeKeys(false)
                                            ->defaultValue([])
                                            ->example(['color' => 'blue', 'description' => 'Workflow to manage article.'])
                                            ->prototype('variable')
                                            ->end()
                                        ->end()
                                    ->end()
                                ->end()
                            ->end()
                            ->arrayNode('transitions', 'transition')
                                ->beforeNormalization()
                                    ->ifArray()
                                    ->then(static function ($transitions) {
                                        $normalizedTransitions = [];
                                        foreach ($transitions as $key => $transition) {
                                            if (\is_array($transition)) {
                                                if (\is_string($key = $transition['key'] ?? $key)) {
                                                    $transition['name'] ??= $key;
                                                }
                                                if (!($transition['name'] ?? false)) {
                                                    throw new InvalidConfigurationException('The "name" option is required for each transition in workflow configuration.');
                                                }
                                                unset($transition['key']);
                                            }
                                            $normalizedTransitions[$key] = $transition;
                                        }

                                        return $normalizedTransitions;
                                    })
                                ->end()
                                ->isRequired()
                                ->requiresAtLeastOneElement()
                                ->prototype('array')
                                    ->children()
                                        ->stringNode('name')
                                            ->isRequired()
                                            ->cannotBeEmpty()
                                        ->end()
                                        ->stringNode('guard')
                                            ->cannotBeEmpty()
                                            ->info('An expression to block the transition.')
                                            ->example('is_fully_authenticated() and is_granted(\'ROLE_JOURNALIST\') and subject.getTitle() == \'My first article\'')
                                        ->end()
                                        ->arrayNode('from')
                                            ->performNoDeepMerging()
                                            ->acceptAndWrap(['backed-enum', 'string'])
                                            ->beforeNormalization()
                                                ->ifArray()
                                                ->then($workflowNormalizeArcs = static function ($arcs) {
                                                    // Fix XML parsing, when only one arc is defined
                                                    if (\array_key_exists('value', $arcs) && \array_key_exists('weight', $arcs)) {
                                                        $arcs = [[
                                                            'place' => $arcs['value'],
                                                            'weight' => $arcs['weight'],
                                                        ]];
                                                    } elseif (\array_key_exists('place', $arcs)) {
                                                        $arcs = [$arcs];
                                                    }

                                                    $normalizedArcs = [];
                                                    foreach ($arcs as $arc) {
                                                        if (\is_string($arc) || $arc instanceof \BackedEnum) {
                                                            $arc = ['place' => $arc];
                                                        } elseif (!\is_array($arc)) {
                                                            throw new InvalidConfigurationException('The "from" arcs must be a list of strings or arrays in workflow configuration.');
                                                        } elseif (\array_key_exists('value', $arc) && \array_key_exists('weight', $arc)) {
                                                            // Fix XML parsing
                                                            $arc = [
                                                                'place' => $arc['value'],
                                                                'weight' => $arc['weight'],
                                                            ];
                                                        }

                                                        if (($arc['place'] ?? null) instanceof \BackedEnum) {
                                                            $arc['place'] = $arc['place']->value;
                                                        }

                                                        $normalizedArcs[] = $arc;
                                                    }

                                                    return $normalizedArcs;
                                                })
                                            ->end()
                                            ->requiresAtLeastOneElement()
                                            ->prototype('array')
                                                ->children()
                                                    ->stringNode('place')
                                                        ->isRequired()
                                                        ->cannotBeEmpty()
                                                    ->end()
                                                    ->integerNode('weight')
                                                        ->defaultValue(1)
                                                        ->min(1)
                                                    ->end()
                                                ->end()
                                            ->end()
                                        ->end()
                                        ->arrayNode('to')
                                            ->performNoDeepMerging()
                                            ->acceptAndWrap(['backed-enum', 'string'])
                                            ->beforeNormalization()
                                                ->ifArray()
                                                ->then($workflowNormalizeArcs)
                                            ->end()
                                            ->requiresAtLeastOneElement()
                                            ->prototype('array')
                                                ->children()
                                                    ->stringNode('place')
                                                        ->isRequired()
                                                        ->cannotBeEmpty()
                                                    ->end()
                                                    ->integerNode('weight')
                                                        ->defaultValue(1)
                                                        ->min(1)
                                                    ->end()
                                                ->end()
                                            ->end()
                                        ->end()
                                        ->integerNode('weight')
                                            ->defaultValue(1)
                                            ->validate()
                                                ->ifTrue(static fn ($v) => $v < 1)
                                                ->thenInvalid('The weight must be greater than 0.')
                                            ->end()
                                        ->end()
                                        ->arrayNode('metadata')
                                            ->useAttributeAsKey('key')
                                            ->normalizeKeys(false)
                                            ->defaultValue([])
                                            ->example(['color' => 'blue', 'description' => 'Workflow to manage article.'])
                                            ->prototype('variable')
                                            ->end()
                                        ->end()
                                    ->end()
                                ->end()
                            ->end()
                            ->arrayNode('metadata')
                                ->useAttributeAsKey('key')
                                ->normalizeKeys(false)
                                ->defaultValue([])
                                ->example(['color' => 'blue', 'description' => 'Workflow to manage article.'])
                                ->prototype('variable')
                                ->end()
                            ->end()
                        ->end()
                        ->validate()
                            ->ifTrue(static fn ($v) => $v['supports'] && isset($v['support_strategy']))
                            ->thenInvalid('"supports" and "support_strategy" cannot be used together.')
                        ->end()
                        ->validate()
                            ->ifTrue(static fn ($v) => !$v['supports'] && !isset($v['support_strategy']))
                            ->thenInvalid('"supports" or "support_strategy" should be configured.')
                        ->end()
                        ->beforeNormalization()
                            ->ifArray()
                            ->then(static function ($values) {
                                // Special case to deal with XML when the user wants an empty array
                                if (\array_key_exists('event_to_dispatch', $values) && null === $values['event_to_dispatch']) {
                                    $values['events_to_dispatch'] = [];
                                    unset($values['event_to_dispatch']);
                                }

                                return $values;
                            })
                        ->end()
                    ->end()
                ->end()
            ->end()
        ;
    }

    public function loadExtension(array $config, ContainerConfigurator $configurator, ContainerBuilder $container): void
    {
        if (!$config['enabled']) {
            return;
        }

        $configurator->import('Resources/config/workflow.php');

        if (!class_exists(Command::class)) {
            $container->removeDefinition('console.command.workflow_dump');
        }

        if ($container->getParameter('kernel.debug')) {
            $configurator->import('Resources/config/workflow_debug.php');
        }

        $registryDefinition = $container->getDefinition('workflow.registry');

        foreach ($config['workflows'] as $name => $workflow) {
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
                        $guardsConfiguration[$eventName][] = new Definition(GuardExpression::class, [new Reference($transitionId), $transition['guard']]);
                    }
                    if ($transition['metadata']) {
                        $transitionsMetadataDefinition->addMethodCall('offsetSet', [new Reference($transitionId), $transition['metadata']]);
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
                                $guardsConfiguration[$eventName][] = new Definition(GuardExpression::class, [new Reference($transitionId), $transition['guard']]);
                            }
                            if ($transition['metadata']) {
                                $transitionsMetadataDefinition->addMethodCall('offsetSet', [new Reference($transitionId), $transition['metadata']]);
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
        }
    }
}
