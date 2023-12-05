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
 * Represents a new PHP property.
 *
 * @internal
 *
 * @author Tobias Nyholm <tobias.nyholm@gmail.com>
 */
class Property
{
    private string $name;
    private string $visibility = 'public';
    private ?string $type = null;
    private $defaultValue;
    private bool $hasDefaultValue = false;
    private array $attributes = [];
    private ?string $comment = null;

    public static function create(string $name): self
    {
        $property = new self();
        $property->setName($name);

        return $property;
    }

    public function setName(string $name): self
    {
        $this->name = $name;

        return $this;
    }

    public function setVisibility(string $visibility): self
    {
        $this->visibility = $visibility;

        return $this;
    }

    public function setType(?string $type): self
    {
        $this->type = $type;

        return $this;
    }

    public function setDefaultValue($defaultValue): self
    {
        $this->defaultValue = $defaultValue;
        $this->hasDefaultValue = true;

        return $this;
    }

    public function addAttribute(Attribute $attribute): self
    {
        $this->attributes[] = $attribute;

        return $this;
    }

    public function setComment(?string $comment): self
    {
        $this->comment = $comment;

        return $this;
    }

    public function toString(): string
    {
        $output = '';
        if ($this->comment) {
            $lines = explode(\PHP_EOL, $this->comment);
            $output .= sprintf('/**'.\PHP_EOL.' * %s'.\PHP_EOL.' */'.\PHP_EOL, implode(\PHP_EOL.' * ', $lines));
        }

        if ($this->attributes) {
            foreach ($this->attributes as $attribute) {
                $output .= $attribute->toString().\PHP_EOL;
            }
        }

        $lastLine = sprintf('%s $%s', $this->type, $this->name);
        $lastLine = sprintf('%s %s', $this->visibility, trim($lastLine));
        if ($this->hasDefaultValue) {
            $lastLine = sprintf('%s = %s', $lastLine, [] === $this->defaultValue ? '[]' : var_export($this->defaultValue, true));
        }
        $lastLine .= ';';

        return $output.$lastLine;
    }
}
