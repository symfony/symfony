<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\DependencyInjection\Loader\Configurator;

use Symfony\Component\Config\Loader\ParamConfigurator;

class EnvConfigurator extends ParamConfigurator
{
    /**
     * @var string[]
     */
    private array $stack;

    public function __construct(string $name)
    {
        $this->stack = explode(':', $name);
    }

    public function __toString(): string
    {
        return '%env('.implode(':', $this->stack).')%';
    }

    /**
     * @return $this
     */
    public function __call(string $name, array $arguments): static
    {
        $processor = strtolower(preg_replace(['/([A-Z]+)([A-Z][a-z])/', '/([a-z\d])([A-Z])/'], '\1_\2', $name));

        $this->custom($processor, ...$arguments);

        return $this;
    }

    /**
     * @return $this
     */
    public function custom(string $processor, ...$args): static
    {
        array_unshift($this->stack, $processor, ...$args);

        return $this;
    }

    /**
     * @return $this
     */
    public function base64(): static
    {
        array_unshift($this->stack, 'base64');

        return $this;
    }

    /**
     * @return $this
     */
    public function bool(): static
    {
        array_unshift($this->stack, 'bool');

        return $this;
    }

    /**
     * @return $this
     */
    public function not(): static
    {
        array_unshift($this->stack, 'not');

        return $this;
    }

    /**
     * @return $this
     */
    public function const(): static
    {
        array_unshift($this->stack, 'const');

        return $this;
    }

    /**
     * @return $this
     */
    public function csv(): static
    {
        array_unshift($this->stack, 'csv');

        return $this;
    }

    /**
     * @return $this
     */
    public function file(): static
    {
        array_unshift($this->stack, 'file');

        return $this;
    }

    /**
     * @return $this
     */
    public function float(): static
    {
        array_unshift($this->stack, 'float');

        return $this;
    }

    /**
     * @return $this
     */
    public function int(): static
    {
        array_unshift($this->stack, 'int');

        return $this;
    }

    /**
     * @return $this
     */
    public function json(): static
    {
        array_unshift($this->stack, 'json');

        return $this;
    }

    /**
     * @return $this
     */
    public function key(string $key): static
    {
        array_unshift($this->stack, 'key', $key);

        return $this;
    }

    /**
     * @return $this
     */
    public function url(): static
    {
        array_unshift($this->stack, 'url');

        return $this;
    }

    /**
     * @return $this
     */
    public function queryString(): static
    {
        array_unshift($this->stack, 'query_string');

        return $this;
    }

    /**
     * @return $this
     */
    public function resolve(): static
    {
        array_unshift($this->stack, 'resolve');

        return $this;
    }

    /**
     * @return $this
     */
    public function default(string $fallbackParam): static
    {
        array_unshift($this->stack, 'default', $fallbackParam);

        return $this;
    }

    /**
     * @return $this
     */
    public function string(): static
    {
        array_unshift($this->stack, 'string');

        return $this;
    }

    /**
     * @return $this
     */
    public function trim(): static
    {
        array_unshift($this->stack, 'trim');

        return $this;
    }

    /**
     * @return $this
     */
    public function require(): static
    {
        array_unshift($this->stack, 'require');

        return $this;
    }

    /**
     * @param class-string<\BackedEnum> $backedEnumClassName
     *
     * @return $this
     */
    public function enum(string $backedEnumClassName): static
    {
        array_unshift($this->stack, 'enum', $backedEnumClassName);

        return $this;
    }
}
