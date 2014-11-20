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
        foreach ($container->findTaggedServiceIds('auto_alias') as $serviceId => $tags) {
            // We don't want to deal with an existing alias.
            if ($container->hasAlias($serviceId)) {
                continue;
            }
            foreach ($tags as $tag) {
                if (!isset($tag['parameter_name'])) {
                    throw new ParameterNotFoundException(sprintf('Missing tag information "parameter_name" on auto_alias service "%s".', $serviceId));
                }

                $parameterName = $tag['parameter_name'];
                if (!$container->hasParameter($parameterName)) {
                    throw new ParameterNotFoundException(sprintf('Missing parameter "%s"', $parameterName));
                }

                if (!isset($tag['format'])) {
                    throw new InvalidArgumentException(sprintf('Missing tag information "format" on auto_alias service "%s".', $serviceId));
                }

                $parameterValue = $container->getParameter($parameterName);
                $aliasId = sprintf($tag['format'], $parameterValue);
                if ($container->hasDefinition($aliasId) || $container->hasAlias($aliasId)) {
                    $container->setAlias($serviceId, new Alias($aliasId));
                }
            }
        }
    }
}
