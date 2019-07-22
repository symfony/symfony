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

    public function __construct(string $rendererService = 'error_renderer', string $rendererTag = 'error_renderer.renderer')
    {
        $this->rendererService = $rendererService;
        $this->rendererTag = $rendererTag;
    }

    /**
     * {@inheritdoc}
     */
    public function process(ContainerBuilder $container)
    {
        if (!$container->hasDefinition($this->rendererService)) {
            return;
        }

        $renderers = $registered = [];
        foreach ($container->findTaggedServiceIds($this->rendererTag, true) as $serviceId => $tags) {
            /** @var ErrorRendererInterface $class */
            $class = $container->getDefinition($serviceId)->getClass();

            foreach ($tags as $tag) {
                $format = $tag['format'] ?? $class::getFormat();
                if (!isset($registered[$format])) {
                    $priority = $tag['priority'] ?? 0;
                    $renderers[$priority][$format] = new Reference($serviceId);
                    $registered[$format] = true;
                }
            }
        }

        if ($renderers) {
            krsort($renderers);
            $renderers = array_merge(...$renderers);
        }

        $definition = $container->getDefinition($this->rendererService);
        $definition->replaceArgument(0, ServiceLocatorTagPass::register($container, $renderers));
    }
}
