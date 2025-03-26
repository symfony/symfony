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
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;

#[\Attribute(\Attribute::TARGET_PARAMETER)]
class Option
{
    private const ALLOWED_TYPES = ['string', 'bool', 'int', 'float', 'array'];

    private string|bool|int|float|array|null $default = null;
    private array|\Closure $suggestedValues;
    private ?int $mode = null;
    private string $typeName = '';
    private bool $allowNull = false;

    /**
     * Represents a console command --option definition.
     *
     * If unset, the `name` value will be inferred from the parameter definition.
     *
     * @param array|string|null                                                          $shortcut        The shortcuts, can be null, a string of shortcuts delimited by | or an array of shortcuts
     * @param array<string|Suggestion>|callable(CompletionInput):list<string|Suggestion> $suggestedValues The values used for input completion
     */
    public function __construct(
        public string $name = '',
        public array|string|null $shortcut = null,
        public string $description = '',
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

        $type = $parameter->getType();
        $name = $parameter->getName();

        if (!$type instanceof \ReflectionNamedType) {
            throw new LogicException(\sprintf('The parameter "$%s" must have a named type. Untyped, Union or Intersection types are not supported for command options.', $name));
        }

        $self->typeName = $type->getName();

        if (!\in_array($self->typeName, self::ALLOWED_TYPES, true)) {
            throw new LogicException(\sprintf('The type "%s" of parameter "$%s" is not supported as a command option. Only "%s" types are allowed.', $self->typeName, $name, implode('", "', self::ALLOWED_TYPES)));
        }

        if (!$parameter->isDefaultValueAvailable()) {
            throw new LogicException(\sprintf('The option parameter "$%s" must declare a default value.', $name));
        }

        if (!$self->name) {
            $self->name = $name;
        }

        $self->default = $parameter->getDefaultValue();
        $self->allowNull = $parameter->allowsNull();

        if ('bool' === $self->typeName) {
            $self->mode = InputOption::VALUE_NONE;
            if (false !== $self->default) {
                $self->mode |= InputOption::VALUE_NEGATABLE;
            }
        } else {
            $self->mode = $self->allowNull ? InputOption::VALUE_OPTIONAL : InputOption::VALUE_REQUIRED;
            if ('array' === $self->typeName) {
                $self->mode |= InputOption::VALUE_IS_ARRAY;
            }
        }

        if (\is_array($self->suggestedValues) && !\is_callable($self->suggestedValues) && 2 === \count($self->suggestedValues) && ($instance = $parameter->getDeclaringFunction()->getClosureThis()) && $instance::class === $self->suggestedValues[0] && \is_callable([$instance, $self->suggestedValues[1]])) {
            $self->suggestedValues = [$instance, $self->suggestedValues[1]];
        }

        return $self;
    }

    /**
     * @internal
     */
    public function toInputOption(): InputOption
    {
        $default = InputOption::VALUE_NONE === (InputOption::VALUE_NONE & $this->mode) ? null : $this->default;
        $suggestedValues = \is_callable($this->suggestedValues) ? ($this->suggestedValues)(...) : $this->suggestedValues;

        return new InputOption($this->name, $this->shortcut, $this->mode, $this->description, $default, $suggestedValues);
    }

    /**
     * @internal
     */
    public function resolveValue(InputInterface $input): mixed
    {
        $value = $input->getOption($this->name);

        if ('bool' !== $this->typeName) {
            return $value;
        }

        if ($this->allowNull && null === $value) {
            return null;
        }

        return $value ?? $this->default;
    }
}
