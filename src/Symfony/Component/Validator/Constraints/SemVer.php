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
 * Validates that a value is a valid semantic version.
 *
 * @see https://semver.org
 *
 * @author Oskar Stark <oskarstark@googlemail.com>
 */
#[\Attribute(\Attribute::TARGET_PROPERTY | \Attribute::TARGET_METHOD | \Attribute::IS_REPEATABLE)]
class SemVer extends Constraint
{
    public const INVALID_SEMVER_ERROR = '3e7a8b8f-4d8f-4c7a-b5e9-1a2b3c4d5e6f';
    public const TOO_LOW_ERROR = 'a0b1c2d3-e4f5-6789-abcd-ef0123456789';
    public const TOO_HIGH_ERROR = 'b1c2d3e4-f5a6-7890-bcde-f01234567890';

    protected const ERROR_NAMES = [
        self::INVALID_SEMVER_ERROR => 'INVALID_SEMVER_ERROR',
        self::TOO_LOW_ERROR => 'TOO_LOW_ERROR',
        self::TOO_HIGH_ERROR => 'TOO_HIGH_ERROR',
    ];

    public string $message;
    public string $minMessage;
    public string $maxMessage;
    public bool $strict;
    public ?string $min;
    public ?string $max;

    /**
     * @param string[]|null $groups
     */
    #[HasNamedArguments]
    public function __construct(
        string $message = 'This value is not a valid semantic version.',
        string $minMessage = 'This value should be {{ min }} or more.',
        string $maxMessage = 'This value should be {{ max }} or less.',
        bool $strict = true,
        ?string $min = null,
        ?string $max = null,
        ?array $groups = null,
        mixed $payload = null,
    ) {
        parent::__construct(null, $groups, $payload);

        $this->message = $message;
        $this->minMessage = $minMessage;
        $this->maxMessage = $maxMessage;
        $this->strict = $strict;
        $this->min = $min;
        $this->max = $max;
    }
}
