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

use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\Compiler\ServiceLocatorTagPass;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;

class TranslatorPass implements CompilerPassInterface
{
    private $translatorServiceId;
    private $readerServiceId;
    private $loaderTag;
    private $debugCommandServiceId;
    private $updateCommandServiceId;

    public function __construct($translatorServiceId = 'translator.default', $readerServiceId = 'translation.loader', $loaderTag = 'translation.loader', $debugCommandServiceId = 'console.command.translation_debug', $updateCommandServiceId = 'console.command.translation_update')
    {
        if ('translation.loader' === $readerServiceId && 2 > \func_num_args()) {
            @trigger_error(sprintf('The default value for $readerServiceId in "%s()" will change in 4.0 to "translation.reader".', __METHOD__), E_USER_DEPRECATED);
        }

        $this->translatorServiceId = $translatorServiceId;
        $this->readerServiceId = $readerServiceId;
        $this->loaderTag = $loaderTag;
        $this->debugCommandServiceId = $debugCommandServiceId;
        $this->updateCommandServiceId = $updateCommandServiceId;
    }

    public function process(ContainerBuilder $container)
    {
        if (!$container->hasDefinition($this->translatorServiceId)) {
            return;
        }

        $loaders = [];
        $loaderRefs = [];
        foreach ($container->findTaggedServiceIds($this->loaderTag, true) as $id => $attributes) {
            $loaderRefs[$id] = new Reference($id);
            $loaders[$id][] = $attributes[0]['alias'];
            if (isset($attributes[0]['legacy-alias'])) {
                $loaders[$id][] = $attributes[0]['legacy-alias'];
            }
        }

        if ($container->hasDefinition($this->readerServiceId)) {
            $definition = $container->getDefinition($this->readerServiceId);
            foreach ($loaders as $id => $formats) {
                foreach ($formats as $format) {
                    $definition->addMethodCall('addLoader', [$format, $loaderRefs[$id]]);
                }
            }
        }

        // Duplicated code to support "translation.reader", to be removed in 4.0
        if ('translation.reader' !== $this->readerServiceId) {
            if ($container->hasDefinition('translation.reader')) {
                $definition = $container->getDefinition('translation.reader');
                foreach ($loaders as $id => $formats) {
                    foreach ($formats as $format) {
                        $definition->addMethodCall('addLoader', [$format, $loaderRefs[$id]]);
                    }
                }
            }
        }

        $container
            ->findDefinition($this->translatorServiceId)
            ->replaceArgument(0, ServiceLocatorTagPass::register($container, $loaderRefs))
            ->replaceArgument(3, $loaders)
        ;

        if (!$container->hasParameter('twig.default_path')) {
            return;
        }

        if ($container->hasDefinition($this->debugCommandServiceId)) {
            $container->getDefinition($this->debugCommandServiceId)->replaceArgument(4, $container->getParameter('twig.default_path'));
        }

        if ($container->hasDefinition($this->updateCommandServiceId)) {
            $container->getDefinition($this->updateCommandServiceId)->replaceArgument(5, $container->getParameter('twig.default_path'));
        }
    }
}
