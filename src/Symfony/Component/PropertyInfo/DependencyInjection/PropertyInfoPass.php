<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\PropertyInfo\DependencyInjection;

use Symfony\Component\DependencyInjection\Argument\IteratorArgument;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\Compiler\PriorityTaggedServiceTrait;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * Adds extractors to the property_info service.
 *
 * @author Kévin Dunglas <dunglas@gmail.com>
 */
class PropertyInfoPass implements CompilerPassInterface
{
    use PriorityTaggedServiceTrait;

    public function process(ContainerBuilder $container): void
    {
        if (!$container->hasDefinition('property_info')) {
            return;
        }

        $definition = $container->getDefinition('property_info');

        $listExtractors = $this->findAndSortTaggedServices('property_info.list_extractor', $container);
        $definition->replaceArgument(0, new IteratorArgument($listExtractors));

        $typeExtractors = $this->findAndSortTaggedServices('property_info.type_extractor', $container);
        $definition->replaceArgument(1, new IteratorArgument($typeExtractors));

        $descriptionExtractors = $this->findAndSortTaggedServices('property_info.description_extractor', $container);
        $definition->replaceArgument(2, new IteratorArgument($descriptionExtractors));

        $accessExtractors = $this->findAndSortTaggedServices('property_info.access_extractor', $container);
        $definition->replaceArgument(3, new IteratorArgument($accessExtractors));

        $initializableExtractors = $this->findAndSortTaggedServices('property_info.initializable_extractor', $container);
        $definition->setArgument(4, new IteratorArgument($initializableExtractors));
    }
}
