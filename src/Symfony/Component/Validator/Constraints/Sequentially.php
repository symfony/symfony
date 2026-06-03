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
use Symfony\Component\Validator\Exception\MissingOptionsException;

/**
 * Use this constraint to sequentially validate nested constraints.
 * Validation for the nested constraints collection will stop at first violation.
 *
 * @author Maxime Steinhausser <maxime.steinhausser@gmail.com>
 */
#[\Attribute(\Attribute::TARGET_CLASS | \Attribute::TARGET_PROPERTY | \Attribute::TARGET_METHOD | \Attribute::IS_REPEATABLE)]
class Sequentially extends Composite
{
    public array|Constraint $constraints = [];

    /**
     * @param Constraint[]|null $constraints An array of validation constraints
     * @param string[]|null     $groups
     */
    public function __construct(array|Constraint|null $constraints = null, ?array $groups = null, mixed $payload = null)
    {
        if (null === $constraints || [] === $constraints) {
            throw new MissingOptionsException(\sprintf('The options "constraints" must be set for constraint "%s".', self::class), ['constraints']);
        }

        $this->constraints = $constraints;

        parent::__construct(null, $groups, $payload);
    }

    protected function getCompositeOption(): string
    {
        return 'constraints';
    }

    public function getTargets(): string|array
    {
        return [self::CLASS_CONSTRAINT, self::PROPERTY_CONSTRAINT];
    }
}
