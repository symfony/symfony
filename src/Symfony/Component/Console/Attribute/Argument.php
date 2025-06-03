<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Console\Attribute;

use Symfony\Component\Console\Completion\CompletionInput;
use Symfony\Component\Console\Completion\Suggestion;
use Symfony\Component\Console\Exception\LogicException;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\String\UnicodeString;

#[\Attribute(\Attribute::TARGET_PARAMETER)]
class Argument
{
    private const ALLOWED_TYPES = ['string', 'bool', 'int', 'float', 'array'];

    private string|bool|int|float|array|null $default = null;
    private array|\Closure $suggestedValues;
    private ?int $mode = null;
    private string $function = '';

    /**
     * Represents a console command <argument> definition.
     *
     * If unset, the `name` value will be inferred from the parameter definition.
     *
     * @param array<string|Suggestion>|callable(CompletionInput):list<string|Suggestion> $suggestedValues The values used for input completion
     */
    public function __construct(
        public string $description = '',
        public string $name = '',
        array|callable $suggestedValues = [],
    ) {
        $this->suggestedValues = \is_callable($suggestedValues) ? $suggestedValues(...) : $suggestedValues;
    }

    /**
     * @internal
     */
    public static function tryFrom(\ReflectionParameter $parameter): ?self
    {
        /** @var self $self */
        if (null === $self = ($parameter->getAttributes(self::class, \ReflectionAttribute::IS_INSTANCEOF)[0] ?? null)?->newInstance()) {
            return null;
        }

        if (($function = $parameter->getDeclaringFunction()) instanceof \ReflectionMethod) {
            $self->function = $function->class.'::'.$function->name;
        } else {
            $self->function = $function->name;
        }

        $type = $parameter->getType();
        $name = $parameter->getName();

        if (!$type instanceof \ReflectionNamedType) {
            throw new LogicException(\sprintf('The parameter "$%s" of "%s()" must have a named type. Untyped, Union or Intersection types are not supported for command arguments.', $name, $self->function));
        }

        $parameterTypeName = $type->getName();

        if (!\in_array($parameterTypeName, self::ALLOWED_TYPES, true)) {
            throw new LogicException(\sprintf('The type "%s" on parameter "$%s" of "%s()" is not supported as a command argument. Only "%s" types are allowed.', $parameterTypeName, $name, $self->function, implode('", "', self::ALLOWED_TYPES)));
        }

        if (!$self->name) {
            $self->name = (new UnicodeString($name))->kebab();
        }

        $self->default = $parameter->isDefaultValueAvailable() ? $parameter->getDefaultValue() : null;

        $self->mode = $parameter->isDefaultValueAvailable() || $parameter->allowsNull() ? InputArgument::OPTIONAL : InputArgument::REQUIRED;
        if ('array' === $parameterTypeName) {
            $self->mode |= InputArgument::IS_ARRAY;
        }

        if (\is_array($self->suggestedValues) && !\is_callable($self->suggestedValues) && 2 === \count($self->suggestedValues) && ($instance = $parameter->getDeclaringFunction()->getClosureThis()) && $instance::class === $self->suggestedValues[0] && \is_callable([$instance, $self->suggestedValues[1]])) {
            $self->suggestedValues = [$instance, $self->suggestedValues[1]];
        }

        return $self;
    }

    /**
     * @internal
     */
    public function toInputArgument(): InputArgument
    {
        $suggestedValues = \is_callable($this->suggestedValues) ? ($this->suggestedValues)(...) : $this->suggestedValues;

        return new InputArgument($this->name, $this->mode, $this->description, $this->default, $suggestedValues);
    }

    /**
     * @internal
     */
    public function resolveValue(InputInterface $input): mixed
    {
        return $input->getArgument($this->name);
    }
}
