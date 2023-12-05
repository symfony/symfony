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
 * Represents a new PHP method.
 *
 * @internal
 *
 * @author Tobias Nyholm <tobias.nyholm@gmail.com>
 */
class Method
{
    private string $name;
    private string $visibility = 'public';
    private ?string $returnType = null;
    private array $arguments = [];
    private ?string $body = '';
    private array $attributes = [];
    private ?string $comment = null;

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

    public function setVisibility(string $visibility): self
    {
        $this->visibility = $visibility;

        return $this;
    }

    public function setReturnType(?string $returnType): self
    {
        $this->returnType = $returnType;

        return $this;
    }

    public function addArgument(string $name, string $type = null, $default = null): self
    {
        $this->arguments[$name] = [$type, $default, 3 === \func_num_args()];

        return $this;
    }

    public function setBody(?string $body): self
    {
        $this->body = $body;

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

    public function toString(string $indentation = ''): string
    {
        $arguments = [];
        foreach ($this->arguments as $name => [$type, $default, $hasDefault]) {
            $argument = '$'.$name;

            if ($type) {
                $argument = sprintf('%s %s', $type, $argument);
            }
            if ($hasDefault) {
                $argument = sprintf('%s = %s', $argument, [] === $default ? '[]' : var_export($default, true));
            }
            $arguments[] = $argument;
        }

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

        $output .= sprintf('%s function %s(%s)', $this->visibility, $this->name, implode(', ', $arguments));
        if ($this->returnType) {
            $output = sprintf('%s: %s', $output, $this->returnType);
        }

        if (null === $this->body) {
            return sprintf('abstract %s;', $output);
        }

        if ('' === $this->body) {
            return sprintf('%s'.\PHP_EOL.'{'.\PHP_EOL.'}', $output);
        }

        $lines = explode(\PHP_EOL, $this->body);

        return sprintf('%s'.\PHP_EOL.'{'.\PHP_EOL.'%s'.\PHP_EOL.'}', $output, $indentation.implode(\PHP_EOL.$indentation, $lines));
    }
}
