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

use Symfony\Component\Validator\Attribute\HasNamedArguments;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\Exception\MissingOptionsException;

/**
 * When applied to an array (or Traversable object), this constraint allows you to apply
 * a collection of constraints to each element of the array.
 *
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
#[\Attribute(\Attribute::TARGET_PROPERTY | \Attribute::TARGET_METHOD | \Attribute::IS_REPEATABLE)]
class All extends Composite
{
    public array|Constraint $constraints = [];

    /**
     * @param array<Constraint>|Constraint|null $constraints
     * @param string[]|null                     $groups
     */
    #[HasNamedArguments]
    public function __construct(mixed $constraints = null, ?array $groups = null, mixed $payload = null)
    {
        if (null === $constraints || [] === $constraints) {
            throw new MissingOptionsException(\sprintf('The options "constraints" must be set for constraint "%s".', self::class), ['constraints']);
        }

        if (!$constraints instanceof Constraint && !\is_array($constraints) || \is_array($constraints) && !array_is_list($constraints)) {
            trigger_deprecation('symfony/validator', '7.3', 'Passing an array of options to configure the "%s" constraint is deprecated, use named arguments instead.', static::class);

            parent::__construct($constraints, $groups, $payload);
        } else {
            $this->constraints = $constraints;

            parent::__construct(null, $groups, $payload);
        }
    }

    /**
     * @deprecated since Symfony 7.4
     */
    public function getDefaultOption(): ?string
    {
        if (0 === \func_num_args() || func_get_arg(0)) {
            trigger_deprecation('symfony/validator', '7.4', 'The %s() method is deprecated.', __METHOD__);
        }

        return 'constraints';
    }

    /**
     * @deprecated since Symfony 7.4
     */
    public function getRequiredOptions(): array
    {
        if (0 === \func_num_args() || func_get_arg(0)) {
            trigger_deprecation('symfony/validator', '7.4', 'The %s() method is deprecated.', __METHOD__);
        }

        return ['constraints'];
    }

    protected function getCompositeOption(): string
    {
        return 'constraints';
    }
}
