<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Config\Builder;

use Symfony\Component\Config\Definition\NodeInterface;

/**
 * Build PHP classes to generate config.
 *
 * @internal
 *
 * @author Tobias Nyholm <tobias.nyholm@gmail.com>
 */
class ClassBuilder
{
    private string $name;

    /** @var Property[] */
    private array $properties = [];

    /** @var Method[] */
    private array $methods = [];
    private array $require = [];
    private array $use = [];
    private array $implements = [];
    private bool $allowExtraKeys = false;

    public function __construct(
        private string $namespace,
        string $name,
        private NodeInterface $node,
        public readonly bool $isRoot = false,
    ) {
        $this->name = ucfirst($this->camelCase($name)).'Config';
    }

    public function getDirectory(): string
    {
        return str_replace('\\', \DIRECTORY_SEPARATOR, $this->namespace);
    }

    public function getFilename(): string
    {
        return $this->name.'.php';
    }

    public function build(): string
    {
        $rootPath = explode(\DIRECTORY_SEPARATOR, $this->getDirectory());
        $require = '';
        foreach ($this->require as $class) {
            // figure out relative path.
            $path = explode(\DIRECTORY_SEPARATOR, $class->getDirectory());
            $path[] = $class->getFilename();
            foreach ($rootPath as $key => $value) {
                if ($path[$key] !== $value) {
                    break;
                }
                unset($path[$key]);
            }
            $require .= \sprintf('require_once __DIR__.\DIRECTORY_SEPARATOR.\'%s\';', implode('\'.\DIRECTORY_SEPARATOR.\'', $path))."\n";
        }
        $use = $require ? "\n" : '';
        foreach (array_keys($this->use) as $statement) {
            $use .= \sprintf('use %s;', $statement)."\n";
        }

        $implements = $this->implements ? 'implements '.implode(', ', $this->implements) : '';
        $body = '';
        foreach ($this->properties as $property) {
            $body .= '    '.$property->getContent()."\n";
        }
        foreach ($this->methods as $method) {
            $lines = explode("\n", $method->getContent());
            foreach ($lines as $line) {
                $body .= ($line ? '    '.$line : '')."\n";
            }
        }

        return strtr('<?php

namespace NAMESPACE;

REQUIREUSE
/**
 * This class is automatically generated to help in creating a config.
 */
class CLASS IMPLEMENTS
{
BODY
}
', ['NAMESPACE' => $this->namespace, 'REQUIRE' => $require, 'USE' => $use, 'CLASS' => $this->getName(), 'IMPLEMENTS' => $implements, 'BODY' => $body]);
    }

    public function addRequire(self $class): void
    {
        $this->require[] = $class;
    }

    public function addUse(string $class): void
    {
        $this->use[$class] = true;
    }

    public function addImplements(string $interface): void
    {
        $this->implements[] = '\\'.ltrim($interface, '\\');
    }

    public function addMethod(string $name, string $body, array $params = []): void
    {
        $this->methods[] = new Method(strtr($body, ['NAME' => $this->camelCase($name)] + $params));
    }

    public function addProperty(string $name, ?string $classType = null, ?string $defaultValue = null): Property
    {
        $property = new Property($name, '_' !== $name[0] ? $this->camelCase($name) : $name);
        if (null !== $classType) {
            $property->setType($classType);
        }
        $this->properties[] = $property;
        $defaultValue = null !== $defaultValue ? \sprintf(' = %s', $defaultValue) : '';
        $property->setContent(\sprintf('private $%s%s;', $property->getName(), $defaultValue));

        return $property;
    }

    public function getProperties(): array
    {
        return $this->properties;
    }

    private function camelCase(string $input): string
    {
        $output = lcfirst(str_replace(' ', '', ucwords(str_replace('_', ' ', $input))));

        return preg_replace('#\W#', '', $output);
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getNamespace(): string
    {
        return $this->namespace;
    }

    public function getFqcn(): string
    {
        return '\\'.$this->namespace.'\\'.$this->name;
    }

    public function setAllowExtraKeys(bool $allowExtraKeys): void
    {
        $this->allowExtraKeys = $allowExtraKeys;
    }

    public function shouldAllowExtraKeys(): bool
    {
        return $this->allowExtraKeys;
    }

    public function getNode(): NodeInterface
    {
        return $this->node;
    }
}
