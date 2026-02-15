<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Routing\Loader;

use Symfony\Component\Config\Loader\FileLoader;
use Symfony\Component\Config\Resource\FileResource;
use Symfony\Component\Routing\Loader\Configurator\Traits\HostTrait;
use Symfony\Component\Routing\RouteCollection;
use Symfony\Component\Yaml\Exception\ParseException;
use Symfony\Component\Yaml\Parser as YamlParser;
use Symfony\Component\Yaml\Yaml;

/**
 * YamlFileLoader loads Yaml routing files.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 * @author Tobias Schultze <http://tobion.de>
 */
class YamlFileLoader extends FileLoader
{
    use ContentLoaderTrait {
        parseImport as doParseImport;
        parseRoute as doParseRoute;
        validate as doValidate;
    }
    use HostTrait;

    private YamlParser $yamlParser;

    /**
     * @throws \InvalidArgumentException When a route can't be parsed because YAML is invalid
     */
    public function load(mixed $file, ?string $type = null): RouteCollection
    {
        $path = $this->locator->locate($file);

        if (!stream_is_local($path)) {
            throw new \InvalidArgumentException(\sprintf('This is not a local file "%s".', $path));
        }

        if (!file_exists($path)) {
            throw new \InvalidArgumentException(\sprintf('File "%s" not found.', $path));
        }

        $this->yamlParser ??= new YamlParser();

        try {
            $parsedConfig = $this->yamlParser->parseFile($path, Yaml::PARSE_CONSTANT);
        } catch (ParseException $e) {
            throw new \InvalidArgumentException(\sprintf('The file "%s" does not contain valid YAML: ', $path).$e->getMessage(), 0, $e);
        }

        $collection = new RouteCollection();
        $collection->addResource(new FileResource($path));

        // empty file
        if (null === $parsedConfig) {
            return $collection;
        }

        // not an array
        if (!\is_array($parsedConfig)) {
            throw new \InvalidArgumentException(\sprintf('The file "%s" must contain a YAML array.', $path));
        }

        $this->loadContent($collection, $parsedConfig, $path, $file);

        return $collection;
    }

    public function supports(mixed $resource, ?string $type = null): bool
    {
        return \is_string($resource) && \in_array(pathinfo($resource, \PATHINFO_EXTENSION), ['yml', 'yaml'], true) && (!$type || 'yaml' === $type);
    }

    /**
     * Parses a route and adds it to the RouteCollection.
     */
    protected function parseRoute(RouteCollection $collection, string $name, array $config, string $path): void
    {
        $this->doParseRoute($collection, $name, $config, $path);
    }

    /**
     * Parses an import and adds the routes in the resource to the RouteCollection.
     */
    protected function parseImport(RouteCollection $collection, array $config, string $path, string $file): void
    {
        $this->doParseImport($collection, $config, $path, $file);
    }

    /**
     * @throws \InvalidArgumentException If one of the provided config keys is not supported,
     *                                   something is missing or the combination is nonsense
     */
    protected function validate(mixed $config, string $name, string $path): void
    {
        $this->doValidate($config, $name, $path);
    }
}
