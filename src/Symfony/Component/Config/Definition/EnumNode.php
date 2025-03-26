<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Config\Definition;

use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;

/**
 * Node which only allows a finite set of values.
 *
 * @author Johannes M. Schmitt <schmittjoh@gmail.com>
 */
class EnumNode extends ScalarNode
{
    private array $values;
    private ?string $enumFqcn = null;

    /**
     * @param class-string<\UnitEnum>|null $enumFqcn
     */
    public function __construct(?string $name, ?NodeInterface $parent = null, array $values = [], string $pathSeparator = BaseNode::DEFAULT_PATH_SEPARATOR, ?string $enumFqcn = null)
    {
        if (!$values && !$enumFqcn) {
            throw new \InvalidArgumentException('$values must contain at least one element.');
        }

        if ($values && $enumFqcn) {
            throw new \InvalidArgumentException('$values or $enumFqcn cannot be both set.');
        }

        if (null !== $enumFqcn) {
            if (!enum_exists($enumFqcn)) {
                throw new \InvalidArgumentException(\sprintf('The "%s" enum does not exist.', $enumFqcn));
            }

            $values = $enumFqcn::cases();
            $this->enumFqcn = $enumFqcn;
        }

        foreach ($values as $value) {
            if (null === $value || \is_scalar($value)) {
                continue;
            }

            if (!$value instanceof \UnitEnum) {
                throw new \InvalidArgumentException(\sprintf('"%s" only supports scalar, enum, or null values, "%s" given.', __CLASS__, get_debug_type($value)));
            }

            if ($value::class !== ($enumClass ??= $value::class)) {
                throw new \InvalidArgumentException(\sprintf('"%s" only supports one type of enum, "%s" and "%s" passed.', __CLASS__, $enumClass, $value::class));
            }
        }

        parent::__construct($name, $parent, $pathSeparator);
        $this->values = $values;
    }

    public function getValues(): array
    {
        return $this->values;
    }

    public function getEnumFqcn(): ?string
    {
        return $this->enumFqcn;
    }

    /**
     * @internal
     */
    public function getPermissibleValues(string $separator): string
    {
        if (is_subclass_of($this->enumFqcn, \BackedEnum::class)) {
            return implode($separator, array_column($this->enumFqcn::cases(), 'value'));
        }

        return implode($separator, array_unique(array_map(static function (mixed $value): string {
            if (!$value instanceof \UnitEnum) {
                return json_encode($value);
            }

            return ltrim(var_export($value, true), '\\');
        }, $this->values)));
    }

    protected function validateType(mixed $value): void
    {
        if ($value instanceof \UnitEnum) {
            return;
        }

        parent::validateType($value);
    }

    protected function finalizeValue(mixed $value): mixed
    {
        $value = parent::finalizeValue($value);

        if (!$this->enumFqcn) {
            if (!\in_array($value, $this->values, true)) {
                throw $this->createInvalidValueException($value);
            }

            return $value;
        }

        if ($value instanceof $this->enumFqcn) {
            return $value;
        }

        if (!is_subclass_of($this->enumFqcn, \BackedEnum::class)) {
            // value is not an instance of the enum, and the enum is not
            // backed, meaning no cast is possible
            throw $this->createInvalidValueException($value);
        }

        if ($value instanceof \UnitEnum && !$value instanceof $this->enumFqcn) {
            throw new InvalidConfigurationException(\sprintf('The value should be part of the "%s" enum, got a value from the "%s" enum.', $this->enumFqcn, get_debug_type($value)));
        }

        if (!\is_string($value) && !\is_int($value)) {
            throw new InvalidConfigurationException(\sprintf('Only strings and integers can be cast to a case of the "%s" enum, got value of type "%s".', $this->enumFqcn, get_debug_type($value)));
        }

        try {
            return $this->enumFqcn::from($value);
        } catch (\TypeError|\ValueError) {
            throw $this->createInvalidValueException($value);
        }
    }

    private function createInvalidValueException(mixed $value): InvalidConfigurationException
    {
        $displayValue = match (true) {
            \is_int($value) => $value,
            \is_string($value) => \sprintf('"%s"', $value),
            default => \sprintf('of type "%s"', get_debug_type($value)),
        };

        $message = \sprintf('The value %s is not allowed for path "%s". Permissible values: %s.', $displayValue, $this->getPath(), $this->getPermissibleValues(', '));
        if ($this->enumFqcn) {
            $message = substr_replace($message, \sprintf(' (cases of the "%s" enum)', $this->enumFqcn), -1, 0);
        }

        $e = new InvalidConfigurationException($message);
        $e->setPath($this->getPath());

        return $e;
    }
}
