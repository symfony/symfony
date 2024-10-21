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

/**
 * Build PHP classes to generate config.
 *
 * @internal
 *
 * @author Alexandre Daubois <alex.daubois@gmail.com>
 */
class TraitBuilder
{
    private string $name;

    /** @var Method[] */
    private array $methods = [];
    private array $use = [];

    public function __construct(
        private string $namespace,
        string $name,
    ) {
        $this->name = ucfirst($this->camelCase($name)).'Trait';
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
        $use = '';
        foreach (array_keys($this->use) as $statement) {
            $use .= \sprintf('use %s;', $statement)."\n";
        }

        $body = '';
        foreach ($this->methods as $method) {
            $lines = explode("\n", $method->getContent());
            foreach ($lines as $line) {
                $body .= ($line ? '    '.$line : '')."\n";
            }
        }

        return strtr('<?php

namespace NAMESPACE;

USE
/**
 * This trait is automatically generated to help in creating a config.
 */
trait TRAIT
{
BODY
}
', ['NAMESPACE' => $this->namespace, 'USE' => $use, 'TRAIT' => $this->getName(), 'BODY' => $body]);
    }

    public function addUse(string $class): void
    {
        $this->use[$class] = true;
    }

    public function addMethod(string $name, string $body, array $params = []): void
    {
        $this->methods[] = new Method(strtr($body, ['NAME' => $this->camelCase($name)] + $params));
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
}
