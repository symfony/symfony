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
 * Validates that a value is a valid date, i.e. its string representation follows the Y-m-d format.
 *
 * @see https://www.php.net/manual/en/datetime.format.php
 *
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
#[\Attribute(\Attribute::TARGET_PROPERTY | \Attribute::TARGET_METHOD | \Attribute::IS_REPEATABLE)]
class Date extends Constraint
{
    public const PATTERN_YMD = '/^Y(?<separator1>[.\-\/])m(?<separator2>[.\-\/])d$/D';
    public const PATTERN_MDY = '/^m(?<separator1>[.\-\/])d(?<separator2>[.\-\/])Y$/D';
    public const PATTERN_DMY = '/^d(?<separator1>[.\-\/])m(?<separator2>[.\-\/])Y$/D';

    public const ACCEPTED_DATE_FORMATS_REGEX = [
        'Y[.-/]m[.-/]d' => self::PATTERN_YMD,
        'm[.-/]d[.-/]Y' => self::PATTERN_MDY,
        'd[.-/]m[.-/]Y' => self::PATTERN_DMY,
    ];

    public const INVALID_FORMAT_ERROR = '69819696-02ac-4a99-9ff0-14e127c4d1bc';
    public const INVALID_DATE_ERROR = '3c184ce5-b31d-4de7-8b76-326da7b2be93';
    public const NOT_SUPPORTED_DATE_FORMAT_ERROR = 'c9627b80-89b7-4ca6-8b29-a1e709c0ca90';

    protected const ERROR_NAMES = [
        self::INVALID_FORMAT_ERROR => 'INVALID_FORMAT_ERROR',
        self::INVALID_DATE_ERROR => 'INVALID_DATE_ERROR',
        self::NOT_SUPPORTED_DATE_FORMAT_ERROR => 'NOT_SUPPORTED_DATE_FORMAT_ERROR',
    ];

    public string $format = 'Y-m-d';
    public string $message = 'This value is not a valid date.';
    public string $messageDateFormatNotAccepted = 'Unsupported format "{{ value }}", only {{ formats }} are supported, with same separators.';

    /**
     * @param array<string,mixed>|null $options
     * @param string[]|null            $groups
     */
    public function __construct(?array $options = null, ?string $message = null, ?array $groups = null, mixed $payload = null)
    {
        parent::__construct($options, $groups, $payload);

        $this->message = $message ?? $this->message;
    }
}
