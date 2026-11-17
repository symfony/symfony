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
 * When applied to an array (or Traversable object), this constraint allows you to apply
 * a collection of constraints to each element of the array.
 *
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
#[\Attribute(\Attribute::TARGET_PROPERTY | \Attribute::TARGET_METHOD | \Attribute::IS_REPEATABLE)]
class All extends Composite
{
    public const INVALID_TYPE_ERROR = '09b29f86-f3c3-4546-bd5e-3b424f42fd89';

    protected const ERROR_NAMES = [
        self::INVALID_TYPE_ERROR => 'INVALID_TYPE_ERROR',
    ];

    public array|Constraint $constraints = [];
    public ?string $invalidTypeMessage = null;

    /**
     * @param array<Constraint>|Constraint|null $constraints
     * @param string[]|null                     $groups
     * @param string|null                       $invalidTypeMessage The message for invalid types, or null to use the default type error
     */
    public function __construct(array|Constraint|null $constraints = null, ?array $groups = null, mixed $payload = null, ?string $invalidTypeMessage = null)
    {
        if (null === $constraints || [] === $constraints) {
            throw new MissingOptionsException(\sprintf('The options "constraints" must be set for constraint "%s".', self::class), ['constraints']);
        }

        $this->constraints = $constraints;
        $this->invalidTypeMessage = $invalidTypeMessage ?? $this->invalidTypeMessage;

        parent::__construct(null, $groups, $payload);
    }

    protected function getCompositeOption(): string
    {
        return 'constraints';
    }
}
