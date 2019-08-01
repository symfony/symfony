<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\ErrorRenderer\DependencyInjection;

use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\Compiler\ServiceLocatorTagPass;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\ErrorRenderer\ErrorRenderer\ErrorRendererInterface;

/**
 * @author Yonel Ceruto <yonelceruto@gmail.com>
 */
class ErrorRendererPass implements CompilerPassInterface
{
    private $rendererService;
    private $rendererTag;
    private $debugCommandService;

    public function __construct(string $rendererService = 'error_renderer', string $rendererTag = 'error_renderer.renderer', string $debugCommandService = 'console.command.error_renderer_debug')
    {
        $this->rendererService = $rendererService;
        $this->rendererTag = $rendererTag;
        $this->debugCommandService = $debugCommandService;
    }

    /**
     * {@inheritdoc}
     */
    public function process(ContainerBuilder $container)
    {
        if (!$container->hasDefinition($this->rendererService)) {
            return;
        }

        $renderers = [];
        foreach ($container->findTaggedServiceIds($this->rendererTag, true) as $serviceId => $tags) {
            /** @var ErrorRendererInterface $class */
            $class = $container->getDefinition($serviceId)->getClass();

            foreach ($tags as $tag) {
                $format = $tag['format'] ?? $class::getFormat();
                $priority = $tag['priority'] ?? 0;
                if (!isset($renderers[$priority][$format])) {
                    $renderers[$priority][$format] = new Reference($serviceId);
                }
            }
        }

        if ($renderers) {
            ksort($renderers);
            $renderers = array_merge(...$renderers);
        }

        $definition = $container->getDefinition($this->rendererService);
        $definition->replaceArgument(0, ServiceLocatorTagPass::register($container, $renderers));

        if ($container->hasDefinition($this->debugCommandService)) {
            $container->getDefinition($this->debugCommandService)->replaceArgument(0, $renderers);
        }
    }
}
