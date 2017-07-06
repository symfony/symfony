<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Translation\DependencyInjection;

use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\Compiler\ServiceLocatorTagPass;

class TranslatorPass implements CompilerPassInterface
{
    private $translatorServiceId;
    private $loaderServiceId;
    private $loaderTag;

    public function __construct($translatorServiceId = 'translator.default', $loaderServiceId = 'translation.loader', $loaderTag = 'translation.loader')
    {
        $this->translatorServiceId = $translatorServiceId;
        $this->loaderServiceId = $loaderServiceId;
        $this->loaderTag = $loaderTag;
    }

    public function process(ContainerBuilder $container)
    {
        if (!$container->hasDefinition($this->translatorServiceId)) {
            return;
        }

        $loaders = array();
        $loaderRefs = array();
        foreach ($container->findTaggedServiceIds($this->loaderTag, true) as $id => $attributes) {
            $loaderRefs[$id] = new Reference($id);
            $loaders[$id][] = $attributes[0]['alias'];
            if (isset($attributes[0]['legacy-alias'])) {
                $loaders[$id][] = $attributes[0]['legacy-alias'];
            }
        }

        if ($container->hasDefinition($this->loaderServiceId)) {
            $definition = $container->getDefinition($this->loaderServiceId);
            foreach ($loaders as $id => $formats) {
                foreach ($formats as $format) {
                    $definition->addMethodCall('addLoader', array($format, $loaderRefs[$id]));
                }
            }
        }

        $container
            ->findDefinition($this->translatorServiceId)
            ->replaceArgument(0, ServiceLocatorTagPass::register($container, $loaderRefs))
            ->replaceArgument(3, $loaders)
        ;
    }
}
