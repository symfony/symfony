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
        if ($this->enumFqcn && is_a($this->enumFqcn, \BackedEnum::class, true)) {
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

        if ($this->enumFqcn) {
            if (is_a($this->enumFqcn, \BackedEnum::class, true)) {
                if (\is_string($value) || \is_int($value)) {
                    try {
                        $case = $this->enumFqcn::tryFrom($value);
                    } catch (\TypeError) {
                        throw new InvalidConfigurationException(\sprintf('The value could not be casted to a case of the "%s" enum. Is the value the same type as the backing type of the enum?', $this->enumFqcn));
                    }

                    if (null !== $case) {
                        return $case;
                    }
                } elseif ($value instanceof \UnitEnum && !$value instanceof $this->enumFqcn) {
                    throw new InvalidConfigurationException(\sprintf('The value should be part of the "%s" enum, got a value from the "%s" enum.', $this->enumFqcn, get_debug_type($value)));
                }
            }

            if ($value instanceof $this->enumFqcn) {
                return $value;
            }

            throw $this->createInvalidValueException($value);
        }

        if (!\in_array($value, $this->values, true)) {
            throw $this->createInvalidValueException($value);
        }

        return $value;
    }

    private function createInvalidValueException(mixed $value): InvalidConfigurationException
    {
        if ($this->enumFqcn) {
            $message = \sprintf('The value %s is not allowed for path "%s". Permissible values: %s (cases of the "%s" enum).', json_encode($value), $this->getPath(), $this->getPermissibleValues(', '), $this->enumFqcn);
        } else {
            $message = \sprintf('The value %s is not allowed for path "%s". Permissible values: %s.', json_encode($value), $this->getPath(), $this->getPermissibleValues(', '));
        }

        $ex = new InvalidConfigurationException($message);
        $ex->setPath($this->getPath());

        return $ex;
    }
}
