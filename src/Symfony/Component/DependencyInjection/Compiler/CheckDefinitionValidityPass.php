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
use Symfony\Component\DependencyInjection\Exception\EnvParameterException;
use Symfony\Component\DependencyInjection\Exception\RuntimeException;

/**
 * This pass validates each definition individually only taking the information
 * into account which is contained in the definition itself.
 *
 * Later passes can rely on the following, and specifically do not need to
 * perform these checks themselves:
 *
 * - non synthetic, non abstract services always have a class set
 * - synthetic services are always public
 *
 * @author Johannes M. Schmitt <schmittjoh@gmail.com>
 */
class CheckDefinitionValidityPass implements CompilerPassInterface
{
    /**
     * Processes the ContainerBuilder to validate the Definition.
     *
     * @throws RuntimeException When the Definition is invalid
     */
    public function process(ContainerBuilder $container)
    {
        foreach ($container->getDefinitions() as $id => $definition) {
            // synthetic service is public
            if ($definition->isSynthetic() && !$definition->isPublic()) {
                throw new RuntimeException(sprintf('A synthetic service ("%s") must be public.', $id));
            }

            // non-synthetic, non-abstract service has class
            if (!$definition->isAbstract() && !$definition->isSynthetic() && !$definition->getClass()) {
                if ($definition->getFactory()) {
                    throw new RuntimeException(sprintf('Please add the class to service "%s" even if it is constructed by a factory since we might need to add method calls based on compile-time checks.', $id));
                }
                if (class_exists($id) || interface_exists($id, false)) {
                    throw new RuntimeException(sprintf(
                         'The definition for "%s" has no class attribute, and appears to reference a '
                        .'class or interface in the global namespace. Leaving out the "class" attribute '
                        .'is only allowed for namespaced classes. Please specify the class attribute '
                        .'explicitly to get rid of this error.',
                        $id
                    ));
                }

                throw new RuntimeException(sprintf(
                    'The definition for "%s" has no class. If you intend to inject '
                   .'this service dynamically at runtime, please mark it as synthetic=true. '
                   .'If this is an abstract definition solely used by child definitions, '
                   .'please add abstract=true, otherwise specify a class to get rid of this error.',
                   $id
                ));
            }

            // tag attribute values must be scalars
            foreach ($definition->getTags() as $name => $tags) {
                foreach ($tags as $attributes) {
                    foreach ($attributes as $attribute => $value) {
                        if (!is_scalar($value) && null !== $value) {
                            throw new RuntimeException(sprintf('A "tags" attribute must be of a scalar-type for service "%s", tag "%s", attribute "%s".', $id, $name, $attribute));
                        }
                    }
                }
            }

            $resolvedId = $container->resolveEnvPlaceholders($id, null, $usedEnvs);
            if (null !== $usedEnvs) {
                throw new EnvParameterException(array($resolvedId), null, 'A service name ("%s") cannot contain dynamic values.');
            }
        }

        foreach ($container->getAliases() as $id => $alias) {
            $resolvedId = $container->resolveEnvPlaceholders($id, null, $usedEnvs);
            if (null !== $usedEnvs) {
                throw new EnvParameterException(array($resolvedId), null, 'An alias name ("%s") cannot contain dynamic values.');
            }
        }
    }
}
