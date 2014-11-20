<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\DependencyInjection\Compiler;

use Symfony\Component\DependencyInjection\Alias;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Exception\InvalidArgumentException;
use Symfony\Component\DependencyInjection\Exception\ParameterNotFoundException;

/**
 * Sets a service to be an alias of another one, given a format pattern.
 */
class AutoAliasServicePass implements CompilerPassInterface
{
    /**
     * {@inheritdoc}
     */
    public function process(ContainerBuilder $container)
    {
        foreach ($container->findTaggedServiceIds('auto_alias') as $service_id => $tags) {
            // We don't want to deal with an existing alias.
            if ($container->hasAlias($service_id)) {
                continue;
            }
            foreach ($tags as $tag) {
                if (!isset($tag['parameter_name'])) {
                    throw new ParameterNotFoundException(sprintf('Missing tag information "parameter_name" on auto_alias service %s', $service_id));
                }

                $parameter_name = $tag['parameter_name'];
                if (!$container->hasParameter($parameter_name)) {
                    throw new ParameterNotFoundException(sprintf('Missing parameter %s', $parameter_name));
                }

                if (!isset($tag['format'])) {
                    throw new InvalidArgumentException(sprintf('Missing tag information "format" on auto_alias service %s', $service_id));
                }

                $parameter_value = $container->getParameter($parameter_name);
                $format = $tag['format'];
                $alias_id = str_replace('%s', $parameter_value, $format);
                if ($container->hasDefinition($alias_id) || $container->hasAlias($alias_id)) {
                    $container->setAlias($service_id, new Alias($alias_id));
                }
            }
        }
    }
}
