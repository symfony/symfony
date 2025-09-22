<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Validator\Mapping\Loader;

use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\Mapping\ClassMetadata;
use Symfony\Component\Yaml\Exception\ParseException;
use Symfony\Component\Yaml\Parser as YamlParser;
use Symfony\Component\Yaml\Yaml;

/**
 * Loads validation metadata from a YAML file.
 *
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class YamlFileLoader extends FileLoader
{
    protected array $classes;

    private ArrayLoader $arrayLoader;
    private YamlParser $yamlParser;

    public function __construct(string $file)
    {
        $this->file = $file;
    }

    public function loadClassMetadata(ClassMetadata $metadata): bool
    {
        $this->classes ??= $this->loadClassesFromYaml();

        return $this->arrayLoader->loadClassMetadata($metadata);
    }

    /**
     * Return the names of the classes mapped in this file.
     *
     * @return class-string[]
     */
    public function getMappedClasses(): array
    {
        $this->classes ??= $this->loadClassesFromYaml();

        return array_keys($this->classes);
    }

    protected function addNamespaceAlias(string $alias, string $namespace): void
    {
        $this->classes ??= $this->loadClassesFromYaml();

        $this->arrayLoader->addNamespaceAlias($alias, $namespace);

        $this->namespaces[$alias] = $namespace;
    }

    /**
     * @deprecated since Symfony 7.4, to be removed in Symfony 8.0
     */
    protected function parseNodes(array $nodes): array
    {
        trigger_deprecation('symfony/validator', '7.4', 'The %s method is deprecated.', __METHOD__);

        $this->classes ??= $this->loadClassesFromYaml();

        return $this->arrayLoader->parseNodes($nodes);
    }

    /**
     * Loads the YAML class descriptions from the given file.
     *
     * @throws \InvalidArgumentException If the file could not be loaded or did
     *                                   not contain a YAML array
     */
    private function parseFile(string $path): array
    {
        try {
            $classes = $this->yamlParser->parseFile($path, Yaml::PARSE_CONSTANT);
        } catch (ParseException $e) {
            throw new \InvalidArgumentException(\sprintf('The file "%s" does not contain valid YAML: ', $path).$e->getMessage(), 0, $e);
        }

        // empty file
        if (null === $classes) {
            return [];
        }

        // not an array
        if (!\is_array($classes)) {
            throw new \InvalidArgumentException(\sprintf('The file "%s" must contain a YAML array.', $this->file));
        }

        return $classes;
    }

    private function loadClassesFromYaml(): array
    {
        parent::__construct($this->file);

        $this->yamlParser ??= new YamlParser();
        $classes = $this->parseFile($this->file);
        $this->arrayLoader = new ArrayLoader($classes);
        unset($classes['namespaces']);

        return $classes;
    }
}
