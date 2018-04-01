<?php

/*
 * This file is part of the Symphony package.
 *
 * (c) Fabien Potencier <fabien@symphony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symphony\Component\Translation\DependencyInjection;

use Symphony\Component\DependencyInjection\Reference;
use Symphony\Component\DependencyInjection\ContainerBuilder;
use Symphony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symphony\Component\DependencyInjection\Exception\RuntimeException;

/**
 * Adds tagged translation.extractor services to translation extractor.
 */
class TranslationExtractorPass implements CompilerPassInterface
{
    private $extractorServiceId;
    private $extractorTag;

    public function __construct(string $extractorServiceId = 'translation.extractor', string $extractorTag = 'translation.extractor')
    {
        $this->extractorServiceId = $extractorServiceId;
        $this->extractorTag = $extractorTag;
    }

    public function process(ContainerBuilder $container)
    {
        if (!$container->hasDefinition($this->extractorServiceId)) {
            return;
        }

        $definition = $container->getDefinition($this->extractorServiceId);

        foreach ($container->findTaggedServiceIds($this->extractorTag, true) as $id => $attributes) {
            if (!isset($attributes[0]['alias'])) {
                throw new RuntimeException(sprintf('The alias for the tag "translation.extractor" of service "%s" must be set.', $id));
            }

            $definition->addMethodCall('addExtractor', array($attributes[0]['alias'], new Reference($id)));
        }
    }
}
