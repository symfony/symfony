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

/**
 * Validates that a value is a valid International Securities Identification Number (ISIN).
 *
 * @see https://en.wikipedia.org/wiki/International_Securities_Identification_Number
 *
 * @author Laurent Masforné <l.masforne@gmail.com>
 */
#[\Attribute(\Attribute::TARGET_PROPERTY | \Attribute::TARGET_METHOD | \Attribute::IS_REPEATABLE)]
class Isin extends Constraint
{
    public const VALIDATION_LENGTH = 12;
    public const VALIDATION_PATTERN = '/[A-Z]{2}[A-Z0-9]{9}[0-9]{1}/';

    public const INVALID_LENGTH_ERROR = '88738dfc-9ed5-ba1e-aebe-402a2a9bf58e';
    public const INVALID_PATTERN_ERROR = '3d08ce0-ded9-a93d-9216-17ac21265b65e';
    public const INVALID_CHECKSUM_ERROR = '32089b-0ee1-93ba-399e-aa232e62f2d29d';

    protected const ERROR_NAMES = [
        self::INVALID_LENGTH_ERROR => 'INVALID_LENGTH_ERROR',
        self::INVALID_PATTERN_ERROR => 'INVALID_PATTERN_ERROR',
        self::INVALID_CHECKSUM_ERROR => 'INVALID_CHECKSUM_ERROR',
    ];

    public string $message = 'This value is not a valid International Securities Identification Number (ISIN).';

    /**
     * @param array<string,mixed>|null $options
     * @param string[]|null            $groups
     */
    #[HasNamedArguments]
    public function __construct(?array $options = null, ?string $message = null, ?array $groups = null, mixed $payload = null)
    {
        if (\is_array($options)) {
            trigger_deprecation('symfony/validator', '7.3', 'Passing an array of options to configure the "%s" constraint is deprecated, use named arguments instead.', static::class);
        }

        parent::__construct($options, $groups, $payload);

        $this->message = $message ?? $this->message;
    }
}
