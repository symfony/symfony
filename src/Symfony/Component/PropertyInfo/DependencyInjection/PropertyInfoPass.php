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

    private $propertyInfoService;
    private $listExtractorTag;
    private $typeExtractorTag;
    private $descriptionExtractorTag;
    private $accessExtractorTag;
    private $initializableExtractorTag;

    public function __construct(string $propertyInfoService = 'property_info', string $listExtractorTag = 'property_info.list_extractor', string $typeExtractorTag = 'property_info.type_extractor', string $descriptionExtractorTag = 'property_info.description_extractor', string $accessExtractorTag = 'property_info.access_extractor', string $initializableExtractorTag = 'property_info.initializable_extractor')
    {
        $this->propertyInfoService = $propertyInfoService;
        $this->listExtractorTag = $listExtractorTag;
        $this->typeExtractorTag = $typeExtractorTag;
        $this->descriptionExtractorTag = $descriptionExtractorTag;
        $this->accessExtractorTag = $accessExtractorTag;
        $this->initializableExtractorTag = $initializableExtractorTag;
    }

    /**
     * {@inheritdoc}
     */
    public function process(ContainerBuilder $container)
    {
        if (!$container->hasDefinition($this->propertyInfoService)) {
            return;
        }

        $definition = $container->getDefinition($this->propertyInfoService);

        $listExtractors = $this->findAndSortTaggedServices($this->listExtractorTag, $container);
        $definition->replaceArgument(0, new IteratorArgument($listExtractors));

        $typeExtractors = $this->findAndSortTaggedServices($this->typeExtractorTag, $container);
        $definition->replaceArgument(1, new IteratorArgument($typeExtractors));

        $descriptionExtractors = $this->findAndSortTaggedServices($this->descriptionExtractorTag, $container);
        $definition->replaceArgument(2, new IteratorArgument($descriptionExtractors));

        $accessExtractors = $this->findAndSortTaggedServices($this->accessExtractorTag, $container);
        $definition->replaceArgument(3, new IteratorArgument($accessExtractors));

        $initializableExtractors = $this->findAndSortTaggedServices($this->initializableExtractorTag, $container);
        $definition->setArgument(4, new IteratorArgument($initializableExtractors));
    }
}
