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

use Symfony\Component\Config\Definition\Configuration;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\Config\Definition\Processor;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Exception\LogicException;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\Workflow\Attribute\AsWorkflow;
use Symfony\Component\Workflow\Attribute\BuildEventNameTrait;
use Symfony\Component\Workflow\WorkflowBundle;
use Symfony\Component\Workflow\WorkflowTrait;

/**
 * Registers the workflows defined with the #[AsWorkflow] attribute.
 *
 * @author Grégoire Pineau <lyrixx@lyrixx.info>
 *
 * @internal
 */
final class WorkflowAttributePass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container): void
    {
        $workflowIds = [];
        foreach ($container->findTaggedServiceIds('workflow') as $id => $tags) {
            foreach ($tags as $tag) {
                if (isset($tag['name'])) {
                    $workflowIds[$tag['name']] = $id;
                }
            }
        }

        $reader = new AttributeReader();
        $registrar = new WorkflowServiceRegistrar();
        $workflowsNode = null;
        foreach ($container->findTaggedServiceIds('.workflow.attribute', true) as $id => $tags) {
            $definition = $container->getDefinition($id);
            $definition->clearTag('.workflow.attribute');

            if (!$container->hasDefinition('workflow.registry')) {
                throw new LogicException(\sprintf('The "%s" service uses the "#[%s]" attribute but workflows are disabled; enable them in the "workflow" configuration.', $id, AsWorkflow::class));
            }

            $class = $container->getParameterBag()->resolveValue($definition->getClass());
            $reflection = $container->getReflectionClass($class);
            if (!$attribute = ($reflection->getAttributes(AsWorkflow::class)[0] ?? null)?->newInstance()) {
                throw new LogicException(\sprintf('The "%s" service is tagged ".workflow.attribute" but its class "%s" does not use the "#[%s]" attribute.', $id, $class, AsWorkflow::class));
            }

            $workflowsNode ??= (new Configuration(new WorkflowBundle(), $container, 'workflow'))->getConfigTreeBuilder()->getRootNode()->find('workflows')->getNode(true);
            try {
                $workflows = (new Processor())->process($workflowsNode, [$reader->read($attribute, $reflection, $container)]);
            } catch (InvalidConfigurationException $e) {
                throw new LogicException(\sprintf('The workflow defined by "%s" is invalid: ', $class).$e->getMessage(), 0, $e);
            }
            $name = array_key_first($workflows);

            if (isset($workflowIds[$name])) {
                throw new LogicException(\sprintf('The workflow "%s" defined by "%s" is already defined by the "%s" service.', $name, $class, $workflowIds[$name]));
            }
            $workflowIds[$name] = $id;
            $workflowId = $registrar->register($container, $name, $workflows[$name]);

            if (self::usesWorkflowTrait($reflection)) {
                $definition->addMethodCall('setWorkflow', [new Reference($workflowId)]);
            }

            $this->resolveListeners($definition, $reflection, $name);
        }

        $placeholderPrefix = \sprintf('workflow.%s.', AsWorkflow::NAME_PLACEHOLDER);
        foreach ($container->getDefinitions() as $id => $definition) {
            foreach ($definition->getTag('kernel.event_listener') as $tag) {
                if (str_starts_with($tag['event'] ?? '', $placeholderPrefix)) {
                    throw new LogicException(\sprintf('The "%s()" listener of the "%s" service listens to a transition or a place without defining the "workflow" argument of its attribute; set it, or move the listener to a class using the "#[%s]" attribute.', $tag['method'] ?? '__invoke', $id, AsWorkflow::class));
                }
            }
        }
    }

    private static function usesWorkflowTrait(\ReflectionClass $class): bool
    {
        foreach ($class->getTraits() as $trait) {
            if (WorkflowTrait::class === $trait->name || self::usesWorkflowTrait($trait)) {
                return true;
            }
        }

        return ($parent = $class->getParentClass()) && self::usesWorkflowTrait($parent);
    }

    /**
     * Makes the listeners of a workflow class listen to the events of its workflow when their attribute does not define the "workflow" argument.
     */
    private function resolveListeners(Definition $definition, \ReflectionClass $class, string $workflowName): void
    {
        // Without a transition or a place, such listeners were built to listen to the events of all the workflows
        $unscopedListeners = [];
        foreach (class_exists(AsEventListener::class) ? [$class, ...$class->getMethods()] : [] as $reflector) {
            foreach ($reflector->getAttributes(AsEventListener::class, \ReflectionAttribute::IS_INSTANCEOF) as $attribute) {
                if (!\in_array(BuildEventNameTrait::class, class_uses($attribute->getName()), true)) {
                    continue;
                }
                $listener = $attribute->newInstance();
                if (1 === substr_count($listener->event, '.')) {
                    $unscopedListeners[$listener->event][$listener->method ?? ($reflector instanceof \ReflectionMethod ? $reflector->name : '')] = true;
                }
            }
        }

        $placeholderPrefix = \sprintf('workflow.%s.', AsWorkflow::NAME_PLACEHOLDER);
        $tags = $definition->getTag('kernel.event_listener');
        $resolved = false;
        foreach ($tags as $i => $tag) {
            $event = $tag['event'] ?? '';
            if (str_starts_with($event, $placeholderPrefix)) {
                $tags[$i]['event'] = \sprintf('workflow.%s.%s', $workflowName, substr($event, \strlen($placeholderPrefix)));
                $resolved = true;
            } elseif (isset($unscopedListeners[$event][$tag['method'] ?? ''])) {
                $tags[$i]['event'] = \sprintf('workflow.%s.%s', $workflowName, substr($event, \strlen('workflow.')));
                $resolved = true;
            }
        }

        if (!$resolved) {
            return;
        }

        $definition->clearTag('kernel.event_listener');
        foreach ($tags as $tag) {
            $definition->addTag('kernel.event_listener', $tag);
        }
    }
}
