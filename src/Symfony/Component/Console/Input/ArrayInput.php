<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Console\Input;

use Symfony\Component\Console\Exception\InvalidArgumentException;
use Symfony\Component\Console\Exception\InvalidOptionException;

/**
 * ArrayInput represents an input provided as an array.
 *
 * Usage:
 *
 *     $input = new ArrayInput(['command' => 'foo:bar', 'foo' => 'bar', '--bar' => 'foobar']);
 *
 * Values without a key fill the argument slots in order:
 *
 *     $input = new ArrayInput(['foo:bar', 'bar', '--bar' => 'foobar']);
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
class ArrayInput extends Input
{
    private array $unparsedParameters = [];

    public function __construct(
        private array $parameters,
        ?InputDefinition $definition = null,
    ) {
        parent::__construct($definition);
    }

    public function getFirstArgument(): ?string
    {
        foreach ($this->parameters as $param => $value) {
            if ($param && \is_string($param) && '-' === $param[0]) {
                continue;
            }

            return $value;
        }

        return null;
    }

    public function hasParameterOption(string|array $values, bool $onlyParams = false): bool
    {
        $values = (array) $values;

        foreach ($this->parameters as $k => $v) {
            if (!\is_int($k)) {
                $v = $k;
            }

            if ($onlyParams && '--' === $v) {
                return false;
            }

            if (\in_array($v, $values)) {
                return true;
            }
        }

        return false;
    }

    public function getParameterOption(string|array $values, string|bool|int|float|array|null $default = false, bool $onlyParams = false): mixed
    {
        $values = (array) $values;

        foreach ($this->parameters as $k => $v) {
            if ($onlyParams && ('--' === $k || (\is_int($k) && '--' === $v))) {
                return $default;
            }

            if (\is_int($k)) {
                if (\in_array($v, $values)) {
                    return true;
                }
            } elseif (\in_array($k, $values)) {
                return $v;
            }
        }

        return $default;
    }

    /**
     * Returns a stringified representation of the args passed to the command.
     */
    public function __toString(): string
    {
        $params = [];
        foreach ($this->parameters as $param => $val) {
            if ($param && \is_string($param) && '-' === $param[0]) {
                $glue = ('-' === $param[1]) ? '=' : ' ';
                if (\is_array($val)) {
                    foreach ($val as $v) {
                        $params[] = $param.('' != $v ? $glue.$this->escapeToken($v) : '');
                    }
                } else {
                    $params[] = $param.('' != $val ? $glue.$this->escapeToken($val) : '');
                }
            } else {
                $params[] = \is_array($val) ? implode(' ', array_map($this->escapeToken(...), $val)) : $this->escapeToken($val);
            }
        }

        return implode(' ', $params);
    }

    protected function parse(): void
    {
        $this->unparsedParameters = [];
        $ignoresExtraArguments = $this->definition->ignoresExtraArguments();
        $remaining = $this->parameters;

        foreach ($this->parameters as $key => $value) {
            if ('--' === $key) {
                return;
            }
            if (\is_string($key) && str_starts_with($key, '--')) {
                $this->addLongOption(substr($key, 2), $value);
            } elseif (\is_string($key) && str_starts_with($key, '-')) {
                $this->addShortOption(substr($key, 1), $value);
            } elseif ($ignoresExtraArguments && ((\is_int($key) && '--' === $value) || !$this->findArgument($key))) {
                // the remaining parameters are for something else: a sub-command, or the arguments after a "--"
                $this->unparsedParameters = $remaining;

                return;
            } elseif (!\is_int($key) || '--' !== $value) {
                // a positional "--" is the argv separator, it binds to nothing
                $this->addArgument($key, $value);
            }
            unset($remaining[$key]);
        }
    }

    /**
     * Returns the parameters left unparsed when the definition ignores extra
     * arguments, converted to argv-shaped tokens.
     *
     * @see InputDefinition::setIgnoreExtraArguments()
     *
     * @return list<string>
     */
    public function getUnparsedTokens(): array
    {
        $tokens = [];
        foreach ($this->unparsedParameters as $key => $value) {
            if ('--' === $key) {
                $tokens[] = '--';
                continue;
            }
            if (\is_string($key) && str_starts_with($key, '--')) {
                foreach (\is_array($value) ? $value : [$value] as $v) {
                    $tokens[] = \in_array($v, [true, null, ''], true) ? $key : $key.'='.$v;
                }
            } elseif (\is_string($key) && str_starts_with($key, '-')) {
                foreach (\is_array($value) ? $value : [$value] as $v) {
                    $tokens[] = $key;
                    if (!\in_array($v, [true, null, ''], true)) {
                        $tokens[] = $v;
                    }
                }
            } else {
                foreach (\is_array($value) ? $value : [$value] as $v) {
                    $tokens[] = $v;
                }
            }
        }

        return $tokens;
    }

    /**
     * Adds a short option value.
     *
     * @throws InvalidOptionException When option given doesn't exist
     */
    private function addShortOption(string $shortcut, mixed $value): void
    {
        if (!$this->definition->hasShortcut($shortcut)) {
            throw new InvalidOptionException(\sprintf('The "-%s" option does not exist.', $shortcut));
        }

        $this->addLongOption($this->definition->getOptionForShortcut($shortcut)->getName(), $value);
    }

    /**
     * Adds a long option value.
     *
     * @throws InvalidOptionException When option given doesn't exist
     * @throws InvalidOptionException When a required value is missing
     */
    private function addLongOption(string $name, mixed $value): void
    {
        if (!$this->definition->hasOption($name)) {
            if (!$this->definition->hasNegation($name)) {
                throw new InvalidOptionException(\sprintf('The "--%s" option does not exist.', $name));
            }

            $optionName = $this->definition->negationToName($name);
            $this->options[$optionName] = false;

            return;
        }

        $option = $this->definition->getOption($name);

        if (null === $value) {
            if ($option->isValueRequired()) {
                throw new InvalidOptionException(\sprintf('The "--%s" option requires a value.', $name));
            }

            if (!$option->isValueOptional()) {
                $value = true;
            }
        }

        $this->options[$name] = $value;
    }

    /**
     * Adds an argument value.
     *
     * @throws InvalidArgumentException When argument given doesn't exist
     */
    private function addArgument(string|int $name, mixed $value): void
    {
        if (!$argument = $this->findArgument($name)) {
            if (\is_string($name) || !$this->definition->hasArgument($name)) {
                throw new InvalidArgumentException(\sprintf('The "%s" argument does not exist.', $name));
            }

            // no free slot left: the value is ignored, as it was when positional values did not bind
            return;
        }

        if (\is_int($name) && $argument->isArray()) {
            $this->arguments[$argument->getName()][] = $value;
        } else {
            $this->arguments[$argument->getName()] = $value;
        }
    }

    /**
     * Returns the argument a parameter binds to: by name, or the first free slot for a positional one.
     */
    private function findArgument(string|int $name): ?InputArgument
    {
        if (\is_string($name)) {
            return $this->definition->hasArgument($name) ? $this->definition->getArgument($name) : null;
        }

        foreach ($this->definition->getArguments() as $argument) {
            if ($argument->isArray() || !\array_key_exists($argument->getName(), $this->arguments)) {
                return $argument;
            }
        }

        return null;
    }
}
