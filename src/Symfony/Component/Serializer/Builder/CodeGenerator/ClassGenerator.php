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
 * Generate a new PHP class.
 *
 * @internal
 *
 * @author Tobias Nyholm <tobias.nyholm@gmail.com>
 */
class ClassGenerator
{
    private ?string $name;
    private ?string $namespace;
    private ?string $extends = null;
    private array $imports = [];
    private array $implements = [];
    /** @var Property[] */
    private array $properties = [];
    /** @var Method[] */
    private array $methods = [];
    /** @var Attribute[] */
    private array $attributes = [];
    private ?string $fileComment = null;
    private ?string $classComment = null;

    public function __construct(string $name = null, string $namespace = null)
    {
        $this->name = $name;
        $this->namespace = $namespace;
    }

    public function setExtends(?string $class): void
    {
        $this->extends = $class;
    }

    public function addImplements(string $class): void
    {
        $this->implements[] = $class;
    }

    public function addAttribute(Attribute $attribute): void
    {
        $this->attributes[] = $attribute;
    }

    public function addMethod(Method $method): void
    {
        $this->methods[] = $method;
    }

    public function addProperty(Property $property): void
    {
        $this->properties[] = $property;
    }

    public function addImport(string $class): void
    {
        $this->imports[] = $class;
    }

    public function setFileComment(?string $fileComment): void
    {
        $this->fileComment = $fileComment;
    }

    public function setClassComment(?string $classComment): void
    {
        $this->classComment = $classComment;
    }

    public function toString(string $indentation = '    '): string
    {
        $output = '<?php'.\PHP_EOL.\PHP_EOL;

        if ($this->fileComment) {
            $lines = explode(\PHP_EOL, $this->fileComment);
            $output .= sprintf('/*'.\PHP_EOL.' * %s'.\PHP_EOL.' */'.\PHP_EOL.\PHP_EOL, implode(\PHP_EOL.' * ', $lines));
        }

        if ($this->namespace) {
            $output .= 'namespace '.$this->namespace.';'.\PHP_EOL.\PHP_EOL;
        }

        if ([] !== $this->imports) {
            foreach ($this->imports as $import) {
                $output .= 'use '.$import.';'.\PHP_EOL;
            }
            $output .= \PHP_EOL;
        }

        if ($this->classComment) {
            $lines = explode(\PHP_EOL, $this->classComment);
            $output .= sprintf('/**'.\PHP_EOL.' * %s'.\PHP_EOL.' */'.\PHP_EOL, implode(\PHP_EOL.' * ', $lines));
        }

        foreach ($this->attributes as $attribute) {
            $output .= $attribute->toString().\PHP_EOL;
        }

        $output .= 'class '.$this->name;
        if ($this->extends) {
            $output .= ' extends '.$this->extends;
        }
        if ($this->implements) {
            $output .= ' implements '.implode(', ', $this->implements);
        }
        $output .= \PHP_EOL.'{'.\PHP_EOL;

        if ([] !== $this->properties) {
            foreach ($this->properties as $property) {
                $lines = explode(\PHP_EOL, $property->toString());
                $output .= $indentation.implode(\PHP_EOL.$indentation, $lines).\PHP_EOL;
            }
            $output .= \PHP_EOL;
        }

        foreach ($this->methods as $method) {
            $lines = explode(\PHP_EOL, $method->toString($indentation));
            $output .= $indentation.implode(\PHP_EOL.$indentation, $lines).\PHP_EOL.\PHP_EOL;
        }

        $output .= '}'.\PHP_EOL;

        return $output;
    }
}
