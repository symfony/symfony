<?php

namespace Symfony\Component\Validator\Constraints;

use Symfony\Component\Validator\Constraint;

#[\Attribute(\Attribute::TARGET_PROPERTY | \Attribute::TARGET_METHOD | \Attribute::IS_REPEATABLE)]
class Timestamp extends Constraint
{

    // I don't know if there is a specific way to generate error code, I just generate uuid v4
    public const TOO_HIGH_ERROR = '23a9537e-d030-48c7-abfb-4220578171c3';
    public const TOO_LOW_ERROR = '96bb47a0-c0e1-43b6-9e5b-34719eafd06c';
    public const INVALID_TIMESTAMP_ERROR = 'df660c56-e0e1-4d42-82ba-d1e9abbea423';

    protected const ERROR_NAMES = [
        self::TOO_HIGH_ERROR => 'TOO_HIGH_ERROR',
        self::TOO_LOW_ERROR => 'TOO_LOW_ERROR',
        self::INVALID_TIMESTAMP_ERROR => 'INVALID_TIMESTAMP_ERROR',
    ];

    public string $message = 'The value "{{ value }}" is not a valid timestamp.';
    public string $greaterThanMessage = 'This value should be greater than {{ compared_value }}.';
    public string $greaterThanOrEqualMessage = 'This value should be greater than or equal to {{ compared_value }}.';
    public string $lessThanMessage = 'This value should be less than {{ compared_value }}.';
    public string $lessThanOrEqualMessage = 'This value should be less than or equal to {{ compared_value }}.';
    public ?string $greaterThan;
    public ?string $greaterThanOrEqual;
    public ?string $lessThan;
    public ?string $lessThanOrEqual;

    public function __construct(
        mixed $options = null,
        ?array $groups = null,
        mixed $payload = null,
        ?string $greaterThan = null,
        ?string $greaterThanOrEqual = null,
        ?string $lessThan = null,
        ?string $lessThanOrEqual = null,
        ?string $greaterThanMessage = null,
        ?string $greaterThanOrEqualMessage = null,
        ?string $lessThanMessage = null,
        ?string $lessThanOrEqualMessage = null,
    )
    {
        $greaterThan ??= $options['greaterThan'] ?? null;
        $greaterThanOrEqual ??= $options['greaterThanOrEqual'] ?? null;
        $lessThan ??= $options['lessThan'] ?? null;
        $lessThanOrEqual ??= $options['lessThanOrEqual'] ?? null;

        parent::__construct($options, $groups, $payload);
        $this->greaterThan = $greaterThan;
        $this->greaterThanOrEqual = $greaterThanOrEqual;
        $this->lessThan = $lessThan;
        $this->lessThanOrEqual = $lessThanOrEqual;
        $this->greaterThanMessage = $greaterThanMessage ?? $this->greaterThanMessage;
        $this->greaterThanOrEqualMessage = $greaterThanOrEqualMessage ?? $this->greaterThanOrEqualMessage;
        $this->lessThanMessage = $lessThanMessage ?? $this->lessThanMessage;
        $this->lessThanOrEqualMessage = $lessThanOrEqualMessage ?? $this->lessThanOrEqualMessage;
    }
}