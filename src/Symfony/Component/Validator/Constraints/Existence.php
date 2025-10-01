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

use Symfony\Component\Validator\Constraint;

/**
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
abstract class Existence extends Composite
{
    public array|Constraint $constraints = [];

    public function __construct(mixed $constraints = null, ?array $groups = null, mixed $payload = null)
    {
        if (!$constraints instanceof Constraint && !\is_array($constraints) || \is_array($constraints) && !array_is_list($constraints)) {
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

    protected function getCompositeOption(): string
    {
        return 'constraints';
    }
}
