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

use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Exception\LogicException;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\Workflow\Attribute\AsWorkflow;

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

            $workflow = $reader->read($attribute, $reflection);
            if (isset($workflowIds[$workflow->name])) {
                throw new LogicException(\sprintf('The workflow "%s" defined by "%s" is already defined by the "%s" service.', $workflow->name, $class, $workflowIds[$workflow->name]));
            }
            $workflowIds[$workflow->name] = $id;
            $workflowId = $registrar->register($container, $workflow);

            if ($reflection->hasMethod('setWorkflow')) {
                $definition->addMethodCall('setWorkflow', [new Reference($workflowId)]);
            }

            $this->resolveListeners($definition, $workflow->name);
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

    /**
     * Completes the name of the events listened to by the listeners of a
     * workflow class that did not define the "workflow" argument.
     */
    private function resolveListeners(Definition $definition, string $workflowName): void
    {
        $placeholderPrefix = \sprintf('workflow.%s.', AsWorkflow::NAME_PLACEHOLDER);
        $tags = $definition->getTag('kernel.event_listener');
        $resolved = false;
        foreach ($tags as $i => $tag) {
            if (str_starts_with($tag['event'] ?? '', $placeholderPrefix)) {
                $tags[$i]['event'] = \sprintf('workflow.%s.%s', $workflowName, substr($tag['event'], \strlen($placeholderPrefix)));
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
