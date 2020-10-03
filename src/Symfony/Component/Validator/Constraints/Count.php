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
 * @Annotation
 * @Target({"PROPERTY", "METHOD", "ANNOTATION"})
 *
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
#[\Attribute(\Attribute::TARGET_PROPERTY | \Attribute::TARGET_METHOD | \Attribute::IS_REPEATABLE)]
class Count extends Constraint
{
    const TOO_FEW_ERROR = 'bef8e338-6ae5-4caf-b8e2-50e7b0579e69';
    const TOO_MANY_ERROR = '756b1212-697c-468d-a9ad-50dd783bb169';
    const NOT_DIVISIBLE_BY_ERROR = DivisibleBy::NOT_DIVISIBLE_BY;

    protected static $errorNames = [
        self::TOO_FEW_ERROR => 'TOO_FEW_ERROR',
        self::TOO_MANY_ERROR => 'TOO_MANY_ERROR',
        self::NOT_DIVISIBLE_BY_ERROR => 'NOT_DIVISIBLE_BY_ERROR',
    ];

    public $minMessage = 'This collection should contain {{ limit }} element or more.|This collection should contain {{ limit }} elements or more.';
    public $maxMessage = 'This collection should contain {{ limit }} element or less.|This collection should contain {{ limit }} elements or less.';
    public $exactMessage = 'This collection should contain exactly {{ limit }} element.|This collection should contain exactly {{ limit }} elements.';
    public $divisibleByMessage = 'The number of elements in this collection should be a multiple of {{ compared_value }}.';
    public $min;
    public $max;
    public $divisibleBy;

    /**
     * {@inheritdoc}
     *
     * @param int|array|null $exactly The expected exact count or a set of options
     */
    public function __construct(
        $exactly = null,
        int $min = null,
        int $max = null,
        int $divisibleBy = null,
        string $exactMessage = null,
        string $minMessage = null,
        string $maxMessage = null,
        string $divisibleByMessage = null,
        array $groups = null,
        $payload = null,
        array $options = []
    ) {
        if (\is_array($exactly)) {
            $options = array_merge($exactly, $options);
            $exactly = $options['value'] ?? null;
        }

        $min = $min ?? $options['min'] ?? null;
        $max = $max ?? $options['max'] ?? null;

        unset($options['value'], $options['min'], $options['max']);

        if (null !== $exactly && null === $min && null === $max) {
            $min = $max = $exactly;
        }

        parent::__construct($options, $groups, $payload);

        $this->min = $min;
        $this->max = $max;
        $this->divisibleBy = $divisibleBy ?? $this->divisibleBy;
        $this->exactMessage = $exactMessage ?? $this->exactMessage;
        $this->minMessage = $minMessage ?? $this->minMessage;
        $this->maxMessage = $maxMessage ?? $this->maxMessage;
        $this->divisibleByMessage = $divisibleByMessage ?? $this->divisibleByMessage;

        if (null === $this->min && null === $this->max && null === $this->divisibleBy) {
            throw new MissingOptionsException(sprintf('Either option "min", "max" or "divisibleBy" must be given for constraint "%s".', __CLASS__), ['min', 'max', 'divisibleBy']);
        }
    }
}
