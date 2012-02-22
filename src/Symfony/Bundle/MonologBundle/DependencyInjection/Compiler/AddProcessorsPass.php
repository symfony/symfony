<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\MonologBundle\DependencyInjection\Compiler;

use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\Reference;

/**
 * Registers processors in Monolg loggers or handlers.
 *
 * @author Christophe Coevoet <stof@notk.org>
 */
class AddProcessorsPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container)
    {
        if (!$container->hasDefinition('monolog.logger')) {
            return;
        }

        foreach ($container->findTaggedServiceIds('monolog.processor') as $id => $tags) {
            foreach ($tags as $tag) {
                if (!empty($tag['channel']) && !empty($tag['handler'])) {
                    throw new \InvalidArgumentException(sprintf('you cannot specify both the "handler" and "channel" attributes for the "monolog.processor" tag on service "%s"', $id));
                }

                if (!empty($tag['handler'])) {
                    $definition = $container->findDefinition(sprintf('monolog.handler.%s', $tag['handler']));
                } elseif (!empty($tag['channel'])) {
                    if ('app' === $tag['channel']) {
                        $definition = $container->getDefinition('monolog.logger');
                    } else {
                        $definition = $container->getDefinition(sprintf('monolog.logger.%s', $tag['channel']));
                    }
                } else {
                    $definition = $container->getDefinition('monolog.logger_prototype');
                }

                if (!empty($tag['method'])) {
                    $processor = array(new Reference($id), $tag['method']);
                } else {
                    // If no method is defined, fallback to use __invoke
                    $processor = new Reference($id);
                }
                $definition->addMethodCall('pushProcessor', array($processor));
            }
        }
    }
}
