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
use Symfony\Component\Console\Exception\RuntimeException;

/**
 * Input is the base class for all concrete Input classes.
 *
 * Three concrete classes are provided by default:
 *
 *  * `ArgvInput`: The input comes from the CLI arguments (argv)
 *  * `StringInput`: The input is provided as a string
 *  * `ArrayInput`: The input is provided as an array
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
abstract class Input implements InputInterface, StreamableInputInterface
{
    protected InputDefinition $definition;
    /** @var resource */
    protected $stream;
    protected array $options = [];
    protected array $arguments = [];
    protected bool $interactive = true;

    public function __construct(?InputDefinition $definition = null)
    {
        if (null === $definition) {
            $this->definition = new InputDefinition();
        } else {
            $this->bind($definition);
            $this->validate();
        }
    }

    public function bind(InputDefinition $definition): void
    {
        $this->arguments = [];
        $this->options = [];
        $this->definition = $definition;

        $this->parse();
    }

    /**
     * Processes command line arguments.
     */
    abstract protected function parse(): void;

    public function validate(): void
    {
        $definition = $this->definition;
        $givenArguments = $this->arguments;

        $missingArguments = array_filter(array_keys($definition->getArguments()), fn ($argument) => !\array_key_exists($argument, $givenArguments) && $definition->getArgument($argument)->isRequired());

        if (\count($missingArguments) > 0) {
            throw new RuntimeException(\sprintf('Not enough arguments (missing: "%s").', implode(', ', $missingArguments)));
        }
    }

    public function isInteractive(): bool
    {
        return $this->interactive;
    }

    public function setInteractive(bool $interactive): void
    {
        $this->interactive = $interactive;
    }

    public function getArguments(): array
    {
        return array_merge($this->definition->getArgumentDefaults(), $this->arguments);
    }

    /**
     * Returns all the given arguments NOT merged with the default values.
     *
     * @param bool $strip Whether to return the raw parameters (false) or the values after the command name (true)
     *z
     * @return array<string|bool|int|float|array<string|bool|int|float|null>|null>
     */
    public function getRawArguments(): array
    {
        return $this->arguments;
    }

    public function getArgument(string $name): mixed
    {
        if (!$this->definition->hasArgument($name)) {
            throw new InvalidArgumentException(\sprintf('The "%s" argument does not exist.', $name));
        }

        return $this->arguments[$name] ?? $this->definition->getArgument($name)->getDefault();
    }

    public function setArgument(string $name, mixed $value): void
    {
        if (!$this->definition->hasArgument($name)) {
            throw new InvalidArgumentException(\sprintf('The "%s" argument does not exist.', $name));
        }

        $this->arguments[$name] = $value;
    }

    public function hasArgument(string $name): bool
    {
        return $this->definition->hasArgument($name);
    }

    public function getOptions(): array
    {
        return array_merge($this->definition->getOptionDefaults(), $this->options);
    }

    /**
     * Returns all the given options NOT merged with the default values.
     *
     * @return array<string|bool|int|float|array<string|bool|int|float|null>|null>
     */
    public function getRawOptions(): array
    {
        return $this->options;
    }

    public function getOption(string $name): mixed
    {
        if ($this->definition->hasNegation($name)) {
            if (null === $value = $this->getOption($this->definition->negationToName($name))) {
                return $value;
            }

            return !$value;
        }

        if (!$this->definition->hasOption($name)) {
            throw new InvalidArgumentException(\sprintf('The "%s" option does not exist.', $name));
        }

        return \array_key_exists($name, $this->options) ? $this->options[$name] : $this->definition->getOption($name)->getDefault();
    }

    public function setOption(string $name, mixed $value): void
    {
        if ($this->definition->hasNegation($name)) {
            $this->options[$this->definition->negationToName($name)] = !$value;

            return;
        } elseif (!$this->definition->hasOption($name)) {
            throw new InvalidArgumentException(\sprintf('The "%s" option does not exist.', $name));
        }

        $this->options[$name] = $value;
    }

    public function hasOption(string $name): bool
    {
        return $this->definition->hasOption($name) || $this->definition->hasNegation($name);
    }

    /**
     * Escapes a token through escapeshellarg if it contains unsafe chars.
     */
    public function escapeToken(string $token): string
    {
        return preg_match('{^[\w-]+$}', $token) ? $token : escapeshellarg($token);
    }

    /**
     * @param resource $stream
     */
    public function setStream($stream): void
    {
        $this->stream = $stream;
    }

    /**
     * @return resource
     */
    public function getStream()
    {
        return $this->stream;
    }

    /**
     * Returns a stringified representation of the options passed to the command.
     *
     * InputArguments MUST be escaped as well as the InputOption values passed to the command.
     *
     * @param string[] $optionNames Name of the options returned. If empty, all options are returned and non-passed or non-existent are ignored.
     *
     * @return list<string>
     */
    public function unparse(array $optionNames = []): array
    {
        $rawOptions = $this->getRawOptions();

        $filteredRawOptions = 0 === \count($optionNames)
            ? $rawOptions
            : array_intersect_key($rawOptions, array_fill_keys($optionNames, ''),
            );

        return array_map(
            fn (string $optionName) => $this->unparseOption(
                $this->definition->getOption($optionName),
                $optionName,
                $filteredRawOptions[$optionName],
            ),
            array_keys($filteredRawOptions),
        );
    }

    /**
     * @param string|bool|int|float|array<string|bool|int|float|null>|null $value
     */
    private function unparseOption(
        InputOption $option,
        string $name,
        array|bool|float|int|string|null $value,
    ): string {
        return match (true) {
            $option->isNegatable() => \sprintf('--%s%s', $value ? '' : 'no-', $name),
            !$option->acceptValue() => \sprintf('--%s', $name),
            $option->isArray() => implode('', array_map(fn ($item) => $this->unparseOptionWithValue($name, $item), $value)),
            default => $this->unparseOptionWithValue($name, $value),
        };
    }

    private function unparseOptionWithValue(
        string $name,
        bool|float|int|string|null $value,
    ): string {
        return \sprintf('--%s=%s', $name, $this->escapeToken($value));
    }
}
