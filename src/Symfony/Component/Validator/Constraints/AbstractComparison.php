<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Validator\Constraints;

use Symfony\Component\PropertyAccess\PropertyAccess;
use Symfony\Component\Validator\Attribute\HasNamedArguments;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\Exception\ConstraintDefinitionException;
use Symfony\Component\Validator\Exception\LogicException;

/**
 * Used for the comparison of values.
 *
 * @author Daniel Holmes <daniel@danielholmes.org>
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
abstract class AbstractComparison extends Constraint
{
    public string $message;
    public mixed $value = null;
    public ?string $propertyPath = null;

    #[HasNamedArguments]
    public function __construct(mixed $value = null, ?string $propertyPath = null, ?string $message = null, ?array $groups = null, mixed $payload = null, ?array $options = null)
    {
        if (\is_array($value)) {
            trigger_deprecation('symfony/validator', '7.2', 'Passing an array of options to configure the "%s" constraint is deprecated, use named arguments instead.', static::class);

            $options = array_merge($value, $options ?? []);
        } elseif (null !== $value) {
            if (\is_array($options)) {
                trigger_deprecation('symfony/validator', '7.2', 'Passing an array of options to configure the "%s" constraint is deprecated, use named arguments instead.', static::class);
            } else {
                $options = [];
            }

            $options['value'] = $value;
        }

        parent::__construct($options, $groups, $payload);

        $this->message = $message ?? $this->message;
        $this->propertyPath = $propertyPath ?? $this->propertyPath;

        if (null === $this->value && null === $this->propertyPath) {
            throw new ConstraintDefinitionException(\sprintf('The "%s" constraint requires either the "value" or "propertyPath" option to be set.', static::class));
        }

        if (null !== $this->value && null !== $this->propertyPath) {
            throw new ConstraintDefinitionException(\sprintf('The "%s" constraint requires only one of the "value" or "propertyPath" options to be set, not both.', static::class));
        }

        if (null !== $this->propertyPath && !class_exists(PropertyAccess::class)) {
            throw new LogicException(\sprintf('The "%s" constraint requires the Symfony PropertyAccess component to use the "propertyPath" option. Try running "composer require symfony/property-access".', static::class));
        }
    }

    public function getDefaultOption(): ?string
    {
        return 'value';
    }
}
