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

    private ?int $mode = null;
    private string $typeName = '';

    /**
     * Represents a console command --option definition.
     *
     * If unset, the `name` and `default` values will be inferred from the parameter definition.
     *
     * @param array|string|null                                              $shortcut        The shortcuts, can be null, a string of shortcuts delimited by | or an array of shortcuts
     * @param scalar|array|null                                              $default         The default value (must be null for self::VALUE_NONE)
     * @param array|callable-string(CompletionInput):list<string|Suggestion> $suggestedValues The values used for input completion
     */
    public function __construct(
        public string $name = '',
        public array|string|null $shortcut = null,
        public string $description = '',
        public string|bool|int|float|array|null $default = null,
        public array|string $suggestedValues = [],
    ) {
        if (\is_string($suggestedValues) && !\is_callable($suggestedValues)) {
            throw new \TypeError(\sprintf('Argument 5 passed to "%s()" must be either an array or a callable-string.', __METHOD__));
        }
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

        if (!$self->name) {
            $self->name = $name;
        }

        if ('bool' === $self->typeName) {
            $self->mode = InputOption::VALUE_NONE | InputOption::VALUE_NEGATABLE;
        } else {
            $self->mode = null !== $self->default || $parameter->isDefaultValueAvailable() ? InputOption::VALUE_OPTIONAL : InputOption::VALUE_REQUIRED;
            if ('array' === $self->typeName) {
                $self->mode |= InputOption::VALUE_IS_ARRAY;
            }
        }

        if (InputOption::VALUE_NONE === (InputOption::VALUE_NONE & $self->mode)) {
            $self->default = null;
        } else {
            $self->default ??= $parameter->isDefaultValueAvailable() ? $parameter->getDefaultValue() : null;
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
        $suggestedValues = \is_callable($this->suggestedValues) ? ($this->suggestedValues)(...) : $this->suggestedValues;

        return new InputOption($this->name, $this->shortcut, $this->mode, $this->description, $this->default, $suggestedValues);
    }

    /**
     * @internal
     */
    public function resolveValue(InputInterface $input): mixed
    {
        if ('bool' === $this->typeName) {
            return $input->hasOption($this->name) && null !== $input->getOption($this->name) ? $input->getOption($this->name) : ($this->default ?? false);
        }

        return $input->hasOption($this->name) ? $input->getOption($this->name) : null;
    }
}
