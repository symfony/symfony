<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\DependencyInjection\Extension;

use Symfony\Component\Config\Definition\ConfigurationInterface;
use Symfony\Component\Config\Definition\Processor;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Exception\BadMethodCallException;
use Symfony\Component\DependencyInjection\Exception\InvalidArgumentException;
use Symfony\Component\DependencyInjection\Exception\LogicException;

/**
 * Provides useful features shared by many extensions.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
abstract class Extension implements ExtensionInterface, ConfigurationExtensionInterface
{
    private array $processedConfigs = [];

    /**
     * @return string|false
     *
     * @deprecated since Symfony 7.4, to be removed in Symfony 8.0 together with XML support.
     */
    public function getXsdValidationBasePath()
    {
        return false;
    }

    /**
     * @return string
     *
     * @deprecated since Symfony 7.4, to be removed in Symfony 8.0 together with XML support.
     */
    public function getNamespace()
    {
        return 'http://example.org/schema/dic/'.$this->getAlias();
    }

    /**
     * Returns the recommended alias to use in XML.
     *
     * This alias is also the mandatory prefix to use when using YAML.
     *
     * This convention is to remove the "Extension" postfix from the class
     * name and then lowercase and underscore the result. So:
     *
     *     AcmeHelloExtension
     *
     * becomes
     *
     *     acme_hello
     *
     * This can be overridden in a sub-class to specify the alias manually.
     *
     * @throws BadMethodCallException When the extension name does not follow conventions
     */
    public function getAlias(): string
    {
        $className = static::class;
        if (!str_ends_with($className, 'Extension')) {
            throw new BadMethodCallException('This extension does not follow the naming convention; you must overwrite the getAlias() method.');
        }
        $classBaseName = substr(strrchr($className, '\\'), 1, -9);

        return Container::underscore($classBaseName);
    }

    /**
     * @return ConfigurationInterface|null
     */
    public function getConfiguration(array $config, ContainerBuilder $container)
    {
        $class = static::class;

        if (str_contains($class, "\0")) {
            return null; // ignore anonymous classes
        }

        $class = substr_replace($class, '\Configuration', strrpos($class, '\\'));
        $class = $container->getReflectionClass($class);

        if (!$class) {
            return null;
        }

        if (!$class->implementsInterface(ConfigurationInterface::class)) {
            throw new LogicException(\sprintf('The extension configuration class "%s" must implement "%s".', $class->getName(), ConfigurationInterface::class));
        }

        if (!($constructor = $class->getConstructor()) || !$constructor->getNumberOfRequiredParameters()) {
            return $class->newInstance();
        }

        return null;
    }

    final protected function processConfiguration(ConfigurationInterface $configuration, array $configs): array
    {
        $processor = new Processor();

        return $this->processedConfigs[] = $processor->processConfiguration($configuration, $configs);
    }

    /**
     * @internal
     */
    final public function getProcessedConfigs(): array
    {
        try {
            return $this->processedConfigs;
        } finally {
            $this->processedConfigs = [];
        }
    }

    /**
     * @throws InvalidArgumentException When the config is not enableable
     */
    protected function isConfigEnabled(ContainerBuilder $container, array $config): bool
    {
        if (!\array_key_exists('enabled', $config)) {
            throw new InvalidArgumentException("The config array has no 'enabled' key.");
        }

        return (bool) $container->getParameterBag()->resolveValue($config['enabled']);
    }
}
