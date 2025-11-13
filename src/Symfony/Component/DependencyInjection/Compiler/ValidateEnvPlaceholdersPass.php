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
use Symfony\Component\Config\Definition\ConfigurationInterface;
use Symfony\Component\Config\Definition\Processor;
use Symfony\Component\DependencyInjection\ContainerBuilder;
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
    private const TYPE_FIXTURES = ['array' => [], 'bool' => false, 'float' => 0.0, 'int' => 0, 'string' => ''];

    private array $extensionConfig = [];

    public function process(ContainerBuilder $container): void
    {
        $this->extensionConfig = [];

        if (!class_exists(BaseNode::class) || !$extensions = $container->getExtensions()) {
            return;
        }

        $resolvingBag = $container->getParameterBag();
        if (!$resolvingBag instanceof EnvPlaceholderParameterBag) {
            return;
        }

        $defaultBag = new ParameterBag($resolvingBag->all());
        $envTypes = $resolvingBag->getProvidedTypes();
        foreach ($resolvingBag->getEnvPlaceholders() + $resolvingBag->getUnusedEnvPlaceholders() as $env => $placeholders) {
            $values = $this->getPlaceholderValues($env, $defaultBag, $envTypes);

            foreach ($placeholders as $placeholder) {
                BaseNode::setPlaceholder($placeholder, $values);
            }
        }

        $processor = new Processor();

        foreach ($extensions as $name => $extension) {
            if (!($extension instanceof ConfigurationExtensionInterface || $extension instanceof ConfigurationInterface)
                || !$config = array_filter($container->getExtensionConfig($name))
            ) {
                // this extension has no semantic configuration or was not called
                continue;
            }

            $config = $resolvingBag->resolveValue($config);

            if ($extension instanceof ConfigurationInterface) {
                $configuration = $extension;
            } elseif (null === $configuration = $extension->getConfiguration($config, $container)) {
                continue;
            }

            $this->extensionConfig[$name] = $processor->processConfiguration($configuration, $config);
        }

        $resolvingBag->clearUnusedEnvPlaceholders();
    }

    /**
     * @internal
     */
    public function getExtensionConfig(): array
    {
        try {
            return $this->extensionConfig;
        } finally {
            $this->extensionConfig = [];
        }
    }

    /**
     * @param array<string, list<string>> $envTypes
     *
     * @return array<string, mixed>
     */
    private function getPlaceholderValues(string $env, ParameterBag $defaultBag, array $envTypes): array
    {
        if (false === $i = strpos($env, ':')) {
            [$default, $defaultType] = $this->getParameterDefaultAndDefaultType("env($env)", $defaultBag);

            return [$defaultType => $default];
        }

        $prefix = substr($env, 0, $i);
        if ('default' === $prefix) {
            $parts = explode(':', $env);
            array_shift($parts); // Remove 'default' prefix
            $parameter = array_shift($parts); // Retrieve and remove parameter

            [$defaultParameter, $defaultParameterType] = $this->getParameterDefaultAndDefaultType($parameter, $defaultBag);

            return [
                $defaultParameterType => $defaultParameter,
                ...$this->getPlaceholderValues(implode(':', $parts), $defaultBag, $envTypes),
            ];
        }

        $values = [];
        foreach ($envTypes[$prefix] ?? ['string'] as $type) {
            $values[$type] = self::TYPE_FIXTURES[$type] ?? null;
        }

        return $values;
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function getParameterDefaultAndDefaultType(string $name, ParameterBag $defaultBag): array
    {
        $default = $defaultBag->has($name) ? $defaultBag->get($name) : self::TYPE_FIXTURES['string'];
        $defaultType = null !== $default ? get_debug_type($default) : 'string';

        return [$default, $defaultType];
    }
}
