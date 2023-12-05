<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Serializer\Builder\CodeGenerator;

/**
 * Represents a new PHP attribute.
 *
 * @internal
 *
 * @author Tobias Nyholm <tobias.nyholm@gmail.com>
 */
class Attribute
{
    private string $name;
    /** @var array<array-key, int|string|bool|float|self|array|null> */
    private array $parameters = [];

    public static function create(string $name): self
    {
        $method = new self();
        $method->setName($name);

        return $method;
    }

    public function setName(string $name): self
    {
        $this->name = $name;

        return $this;
    }

    public function addParameter(?string $name, self|int|string|bool|float|null|array $value): self
    {
        if (null === $name) {
            $this->parameters[] = $value;
        } else {
            $this->parameters[$name] = $value;
        }

        return $this;
    }

    public function toString(bool $nested = false): string
    {
        $parameters = [];
        foreach ($this->parameters as $name => $value) {
            $parameter = '';
            if (\is_string($name)) {
                $parameter .= $name.': ';
            }
            $parameters[] = $parameter.$this->getValue($value);
        }

        if ([] === $parameters && !$nested) {
            $output = $this->name;
        } else {
            $output = sprintf('%s(%s)', $this->name, implode(', ', $parameters));
        }

        if ($nested) {
            return 'new '.$output;
        }

        return sprintf('#[%s]', $output);
    }

    private function getValue($value): string
    {
        if (\is_array($value)) {
            $value = sprintf('[%s]', implode(', ', array_map(fn ($value) => $this->getValue($value), $value)));
        } elseif ($value instanceof self) {
            $value = $value->toString(true);
        } elseif (\is_string($value)) {
            $value = '"'.$value.'"';
        } else {
            $value = var_export($value, true);
        }

        return $value;
    }
}
