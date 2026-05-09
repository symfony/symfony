<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\DependencyInjection\Argument;

use Symfony\Component\DependencyInjection\Exception\InvalidArgumentException;

/**
 * Represents an environment variable presented as an invokable and Stringable wrapper.
 *
 * @author Nicolas Grekas <p@tchwork.com>
 */
class EnvClosureArgument implements ArgumentInterface
{
    use ArgumentTrait;

    public function __construct(
        private string $value,
        private mixed $default = null,
        private bool $stringable = false,
    ) {
        if ($stringable && !\is_string($default ?? '')) {
            throw new InvalidArgumentException('The default value of a stringable EnvClosureArgument must be a string or null.');
        }
    }

    public function getValue(): string
    {
        return $this->value;
    }

    /**
     * Hides the env expression from compiler passes that walk arguments via {@see ArgumentInterface::getValues()},
     * so that env placeholders inside EnvClosureArgument are not resolved at compile time and stay refreshable at runtime.
     */
    public function getValues(): array
    {
        return [];
    }

    public function setValues(array $values): void
    {
        if ($values) {
            throw new InvalidArgumentException('An EnvClosureArgument cannot hold values.');
        }
    }

    public function getDefault(): mixed
    {
        return $this->default;
    }

    public function isStringable(): bool
    {
        return $this->stringable;
    }
}
