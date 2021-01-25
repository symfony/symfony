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

use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\EnvVarProcessor;
use Symfony\Component\DependencyInjection\EnvVarProcessorInterface;
use Symfony\Component\DependencyInjection\Exception\InvalidArgumentException;
use Symfony\Component\DependencyInjection\ParameterBag\EnvPlaceholderParameterBag;
use Symfony\Component\DependencyInjection\Reference;

/**
 * Creates the container.env_var_processors_locator service.
 *
 * @author Nicolas Grekas <p@tchwork.com>
 */
class RegisterEnvVarProcessorsPass implements CompilerPassInterface
{
    private const ALLOWED_TYPES = ['array', 'bool', 'float', 'int', 'string'];

    public function process(ContainerBuilder $container)
    {
        $bag = $container->getParameterBag();
        $types = [];
        $processors = [];
        foreach ($container->findTaggedServiceIds('container.env_var_processor') as $id => $tags) {
            if (!$r = $container->getReflectionClass($class = $container->getDefinition($id)->getClass())) {
                throw new InvalidArgumentException(sprintf('Class "%s" used for service "%s" cannot be found.', $class, $id));
            } elseif (!$r->isSubclassOf(EnvVarProcessorInterface::class)) {
                throw new InvalidArgumentException(sprintf('Service "%s" must implement interface "%s".', $id, EnvVarProcessorInterface::class));
            }
            foreach ($class::getProvidedTypes() as $prefix => $type) {
                $processors[$prefix] = new Reference($id);
                $types[$prefix] = self::validateProvidedTypes($type, $class);
            }
        }

        if ($bag instanceof EnvPlaceholderParameterBag) {
            foreach (EnvVarProcessor::getProvidedTypes() as $prefix => $type) {
                if (!isset($types[$prefix])) {
                    $types[$prefix] = self::validateProvidedTypes($type, EnvVarProcessor::class);
                }
            }
            $bag->setProvidedTypes($types);
        }

        if ($processors) {
            $container->setAlias('container.env_var_processors_locator', (string) ServiceLocatorTagPass::register($container, $processors))
                ->setPublic(true)
            ;
        }
    }

    private static function validateProvidedTypes(string $types, string $class): array
    {
        $types = explode('|', $types);

        foreach ($types as $type) {
            if (!\in_array($type, self::ALLOWED_TYPES)) {
                throw new InvalidArgumentException(sprintf('Invalid type "%s" returned by "%s::getProvidedTypes()", expected one of "%s".', $type, $class, implode('", "', self::ALLOWED_TYPES)));
            }
        }

        return $types;
    }
}
