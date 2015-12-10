<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\FrameworkBundle\DependencyInjection\Compiler;

use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;

/**
 * Adds extractors to the property_info service.
 *
 * @author Kévin Dunglas <dunglas@gmail.com>
 */
class PropertyInfoPass implements CompilerPassInterface
{
    /**
     * {@inheritdoc}
     */
    public function process(ContainerBuilder $container)
    {
        if (!$container->hasDefinition('property_info')) {
            return;
        }

        $definition = $container->getDefinition('property_info');

        $listExtractors = $this->findAndSortTaggedServices('property_info.list_extractor', $container);
        $definition->replaceArgument(0, $listExtractors);

        $typeExtractors = $this->findAndSortTaggedServices('property_info.type_extractor', $container);
        $definition->replaceArgument(1, $typeExtractors);

        $descriptionExtractors = $this->findAndSortTaggedServices('property_info.description_extractor', $container);
        $definition->replaceArgument(2, $descriptionExtractors);

        $accessExtractors = $this->findAndSortTaggedServices('property_info.access_extractor', $container);
        $definition->replaceArgument(3, $accessExtractors);
    }

    /**
     * Finds all services with the given tag name and order them by their priority.
     *
     * @param string           $tagName
     * @param ContainerBuilder $container
     *
     * @return array
     */
    private function findAndSortTaggedServices($tagName, ContainerBuilder $container)
    {
        $services = $container->findTaggedServiceIds($tagName);

        $sortedServices = array();
        foreach ($services as $serviceId => $tags) {
            foreach ($tags as $attributes) {
                $priority = isset($attributes['priority']) ? $attributes['priority'] : 0;
                $sortedServices[$priority][] = new Reference($serviceId);
            }
        }

        if (empty($sortedServices)) {
            return array();
        }

        krsort($sortedServices);

        // Flatten the array
        return call_user_func_array('array_merge', $sortedServices);
    }
}
