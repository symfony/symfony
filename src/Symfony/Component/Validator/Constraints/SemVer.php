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
 * @author Oskar Stark <oskarstark@googlemail.com>
 */
#[\Attribute(\Attribute::TARGET_PROPERTY | \Attribute::TARGET_METHOD | \Attribute::IS_REPEATABLE)]
class SemVer extends Constraint
{
    public const INVALID_SEMVER_ERROR = '3e7a8b8f-4d8f-4c7a-b5e9-1a2b3c4d5e6f';

    protected const ERROR_NAMES = [
        self::INVALID_SEMVER_ERROR => 'INVALID_SEMVER_ERROR',
    ];

    public string $message;
    public bool $strict;

    /**
     * @param string[]|null $groups
     */
    #[HasNamedArguments]
    public function __construct(
        string $message = 'This value is not a valid semantic version.',
        bool $strict = true,
        ?array $groups = null,
        mixed $payload = null,
    ) {
        parent::__construct(null, $groups, $payload);

        $this->message = $message;
        $this->strict = $strict;
    }
}
