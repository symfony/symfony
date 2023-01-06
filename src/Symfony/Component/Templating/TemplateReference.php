<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Templating;

/**
 * Internal representation of a template.
 *
 * @author Victor Berchet <victor@suumit.com>
 */
class TemplateReference implements TemplateReferenceInterface
{
    protected $parameters;

    public function __construct(string $name = null, string $engine = null)
    {
        $this->parameters = [
            'name' => $name,
            'engine' => $engine,
        ];
    }

    public function __toString(): string
    {
        return $this->getLogicalName();
    }

    public function set(string $name, string $value): static
    {
        if (\array_key_exists($name, $this->parameters)) {
            $this->parameters[$name] = $value;
        } else {
            throw new \InvalidArgumentException(sprintf('The template does not support the "%s" parameter.', $name));
        }

        return $this;
    }

    public function get(string $name): string
    {
        if (\array_key_exists($name, $this->parameters)) {
            return $this->parameters[$name];
        }

        throw new \InvalidArgumentException(sprintf('The template does not support the "%s" parameter.', $name));
    }

    public function all(): array
    {
        return $this->parameters;
    }

    public function getPath(): string
    {
        return $this->parameters['name'];
    }

    public function getLogicalName(): string
    {
        return $this->parameters['name'];
    }
}
