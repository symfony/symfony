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
 * Validates a collection of values against a sum constraint.
 *
 * @author Kasper Hansen <kasper.h90@gmail.com>
 */
#[\Attribute(\Attribute::TARGET_PROPERTY | \Attribute::TARGET_METHOD | \Attribute::IS_REPEATABLE)]
class Sum extends Constraint
{
    public const NOT_NUMERIC_ERROR = 'eca869dd-6625-42e3-ab24-cc0f58f688ea';
    public const NOT_EQUAL_SUM_ERROR = 'e2f062a3-b094-49a3-9498-0a0f4bc3c48d';
    public const TOO_LOW_ERROR = 'fe85a414-c594-4464-8416-7421df16a4c6';
    public const TOO_HIGH_ERROR = '342c7673-dfdc-4165-b934-859cf34af9a4';

    protected const ERROR_NAMES = [
        self::NOT_NUMERIC_ERROR => 'NOT_NUMERIC_ERROR',
        self::NOT_EQUAL_SUM_ERROR => 'NOT_EQUAL_SUM_ERROR',
        self::TOO_LOW_ERROR => 'TOO_LOW_ERROR',
        self::TOO_HIGH_ERROR => 'TOO_HIGH_ERROR',
    ];

    public string $exactMessage = 'The sum of the collection should be exactly {{ limit }}.';
    public string $minMessage = 'The sum of the collection should be {{ limit }} or more.';
    public string $maxMessage = 'The sum of the collection should be {{ limit }} or less.';
    public string $notNumericMessage = 'The value {{ value }} is not numeric.';
    public ?float $min = null;
    public ?float $max = null;

    /**
     * @param float|null    $exactly The exact expected sum of the collection
     * @param float|null    $min     Minimum expected sum of the collection
     * @param float|null    $max     Maximum expected sum of the collection
     * @param string[]|null $groups
     */
    public function __construct(
        ?float $exactly = null,
        ?float $min = null,
        ?float $max = null,
        ?string $exactMessage = null,
        ?string $minMessage = null,
        ?string $maxMessage = null,
        ?string $notNumericMessage = null,
        ?array $groups = null,
        mixed $payload = null,
    ) {
        if (null !== $exactly && null === $min && null === $max) {
            $min = $max = $exactly;
        }

        parent::__construct(null, $groups, $payload);

        $this->min = $min;
        $this->max = $max;
        $this->exactMessage = $exactMessage ?? $this->exactMessage;
        $this->minMessage = $minMessage ?? $this->minMessage;
        $this->maxMessage = $maxMessage ?? $this->maxMessage;
        $this->notNumericMessage = $notNumericMessage ?? $this->notNumericMessage;

        if (null === $this->min && null === $this->max) {
            throw new MissingOptionsException(\sprintf('Either option "min" or "max" must be given for constraint "%s".', __CLASS__), ['min', 'max']);
        }
    }
}
