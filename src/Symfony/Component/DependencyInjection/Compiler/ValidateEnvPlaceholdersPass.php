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

use Symfony\Component\Config\Definition\BaseNode;
use Symfony\Component\Config\Definition\Processor;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Exception\LogicException;
use Symfony\Component\DependencyInjection\Extension\ConfigurationExtensionInterface;
use Symfony\Component\DependencyInjection\ParameterBag\EnvPlaceholderParameterBag;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBag;

/**
 * Validates environment variable placeholders used in extension configuration with dummy values.
 *
 * @author Roland Franssen <franssen.roland@gmail.com>
 */
class ValidateEnvPlaceholdersPass implements CompilerPassInterface
{
    private static $typeFixtures = array('array' => array(), 'bool' => false, 'float' => 0.0, 'int' => 0, 'string' => '');

    /**
     * {@inheritdoc}
     */
    public function process(ContainerBuilder $container)
    {
        if (!class_exists(BaseNode::class) || !$extensions = $container->getExtensions()) {
            return;
        }

        $resolvingBag = $container->getParameterBag();
        if (!$resolvingBag instanceof EnvPlaceholderParameterBag) {
            return;
        }

        $defaultBag = new ParameterBag($container->getParameterBag()->all());
        $envTypes = $resolvingBag->getProvidedTypes();
        try {
            foreach ($resolvingBag->getEnvPlaceholders() + $resolvingBag->getUnusedEnvPlaceholders() as $env => $placeholders) {
                $prefix = (false === $i = strpos($env, ':')) ? 'string' : substr($env, 0, $i);
                $types = $envTypes[$prefix] ?? array('string');
                $default = ($hasEnv = (false === $i && $defaultBag->has("env($env)"))) ? $defaultBag->get("env($env)") : null;

                if (null !== $default && !in_array($type = self::getType($default), $types, true)) {
                    throw new LogicException(sprintf('Invalid type for env parameter "env(%s)". Expected "%s", but got "%s".', $env, implode('", "', $types), $type));
                }

                $values = array();
                foreach ($types as $type) {
                    $values[$type] = $hasEnv ? $default : self::$typeFixtures[$type] ?? null;
                }
                foreach ($placeholders as $placeholder) {
                    BaseNode::setPlaceholder($placeholder, $values);
                }
            }

            $processor = new Processor();

            foreach ($extensions as $name => $extension) {
                if (!$extension instanceof ConfigurationExtensionInterface || !$config = $container->getExtensionConfig($name)) {
                    // this extension has no semantic configuration or was not called
                    continue;
                }

                $config = $resolvingBag->resolveValue($config);

                if (null === $configuration = $extension->getConfiguration($config, $container)) {
                    continue;
                }

                $processor->processConfiguration($configuration, $config);
            }
        } finally {
            BaseNode::resetPlaceholders();
        }
    }

    private static function getType($value): string
    {
        switch ($type = \gettype($value)) {
            case 'boolean':
                return 'bool';
            case 'double':
                return 'float';
            case 'integer':
                return 'int';
        }

        return $type;
    }
}
