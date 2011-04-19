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

use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\DefinitionDecorator;

/**
 * Replaces the default logger by another one with its own channel for tagged services.
 *
 * @author Christophe Coevoet <stof@notk.org>
 */
class LoggerChannelPass implements CompilerPassInterface
{
    protected $channels = array();

    public function process(ContainerBuilder $container)
    {
        if (!$container->hasDefinition('monolog.logger')) {
            return;
        }

        foreach ($container->findTaggedServiceIds('monolog.logger') as $id => $tags) {
            foreach ($tags as $tag) {
                if (!empty($tag['channel']) && 'app' !== $tag['channel']) {
                    $definition = $container->getDefinition($id);
                    $loggerId = sprintf('monolog.logger.%s', $tag['channel']);
                    $this->createLogger($tag['channel'], $loggerId, $container);
                    foreach ($definition->getArguments() as $index => $argument) {
                        if ($argument instanceof Reference && 'logger' === (string) $argument) {
                            $definition->setArgument($index, new Reference($loggerId, $argument->getInvalidBehavior(), $argument->isStrict()));
                        }
                    }
                }
            }
        }
    }

    protected function createLogger($channel, $loggerId, ContainerBuilder $container)
    {
        if (!in_array($channel, $this->channels)) {
            $logger = new DefinitionDecorator('monolog.logger_prototype');
            $logger->setArgument(0, $channel);
            $container->setDefinition($loggerId, $logger);
            array_push($this->channels, $channel);
        }
    }
}
