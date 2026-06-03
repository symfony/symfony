<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\DependencyInjection\Loader;

use Symfony\Component\DependencyInjection\Exception\InvalidArgumentException;
use Symfony\Component\DependencyInjection\Exception\RuntimeException;
use Symfony\Component\DependencyInjection\Extension\ExtensionInterface;
use Symfony\Component\Yaml\Exception\ParseException;
use Symfony\Component\Yaml\Parser as YamlParser;
use Symfony\Component\Yaml\Yaml;

/**
 * YamlFileLoader loads YAML files service definitions.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
class YamlFileLoader extends FileLoader
{
    use ContentLoaderTrait;

    protected bool $autoRegisterAliasesForSinglyImplementedInterfaces = false;

    private YamlParser $yamlParser;

    public function load(mixed $resource, ?string $type = null): mixed
    {
        $path = $this->locator->locate($resource);

        $content = $this->loadFile($path);

        $this->container->fileExists($path);

        // empty file
        if (null === $content) {
            return null;
        }

        ++$this->importing;
        try {
            $this->loadContent($content, $path);

            // per-env configuration
            if ($this->env && isset($content[$when = 'when@'.$this->env])) {
                if (!\is_array($content[$when])) {
                    throw new InvalidArgumentException(\sprintf('The "%s" key should contain an array in "%s".', $when, $path));
                }

                $this->loadContent($content[$when], $path);
            }
        } finally {
            --$this->importing;
        }
        $this->loadExtensionConfigs();

        return null;
    }

    public function supports(mixed $resource, ?string $type = null): bool
    {
        if (!\is_string($resource)) {
            return false;
        }

        if (null === $type && \in_array(pathinfo($resource, \PATHINFO_EXTENSION), ['yaml', 'yml'], true)) {
            return true;
        }

        return \in_array($type, ['yaml', 'yml'], true);
    }

    /**
     * Loads a YAML file.
     *
     * @throws InvalidArgumentException when the given file is not a local file or when it does not exist
     */
    protected function loadFile(string $file): ?array
    {
        if (!class_exists(YamlParser::class)) {
            throw new RuntimeException('Unable to load YAML config files as the Symfony Yaml Component is not installed. Try running "composer require symfony/yaml".');
        }

        if (!stream_is_local($file)) {
            throw new InvalidArgumentException(\sprintf('This is not a local file "%s".', $file));
        }

        if (!is_file($file)) {
            throw new InvalidArgumentException(\sprintf('The file "%s" does not exist.', $file));
        }

        $this->yamlParser ??= new YamlParser();

        try {
            $configuration = $this->yamlParser->parseFile($file, Yaml::PARSE_CONSTANT | Yaml::PARSE_CUSTOM_TAGS);
        } catch (ParseException $e) {
            throw new InvalidArgumentException(\sprintf('The file "%s" does not contain valid YAML: ', $file).$e->getMessage(), 0, $e);
        }

        return $this->validate($configuration, $file);
    }

    /**
     * Validates a YAML file.
     *
     * @throws InvalidArgumentException When service file is not valid
     */
    private function validate(mixed $content, string $file): ?array
    {
        if (null === $content) {
            return $content;
        }

        if (!\is_array($content)) {
            throw new InvalidArgumentException(\sprintf('The service file "%s" is not valid. It should contain an array.', $file));
        }

        foreach ($content as $namespace => $data) {
            if (\in_array($namespace, ['imports', 'parameters', 'services'], true) || str_starts_with($namespace, 'when@')) {
                continue;
            }

            if (!$this->prepend && !$this->container->hasExtension($namespace)) {
                $extensionNamespaces = array_filter(array_map(static fn (ExtensionInterface $ext) => $ext->getAlias(), $this->container->getExtensions()));
                throw new InvalidArgumentException(UndefinedExtensionHandler::getErrorMessage($namespace, $file, $namespace, $extensionNamespaces));
            }
        }

        return $content;
    }
}
