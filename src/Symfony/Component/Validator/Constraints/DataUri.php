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
 * Validates that a value is a valid data URI string.
 *
 * @author Kev <https://github.com/symfonyaml>
 */
#[\Attribute(\Attribute::TARGET_PROPERTY | \Attribute::TARGET_METHOD | \Attribute::IS_REPEATABLE)]
class DataUri extends Constraint
{
    public const INVALID_DATA_URI_ERROR = 'b9e175d1-8d7a-4e28-bf65-ad2448a3b3cf';

    protected const ERROR_NAMES = [
        self::INVALID_DATA_URI_ERROR => 'INVALID_DATA_URI_ERROR',
    ];

    public string $message = 'This value is not a valid data URI.';

    /**
     * @param array<string,mixed>|null $options
     * @param string[]|null            $groups
     */
    public function __construct(
        ?array $options = null,
        ?string $message = null,
        ?array $groups = null,
        mixed $payload = null,
    ) {
        parent::__construct($options ?? [], $groups, $payload);

        $this->message = $message ?? $this->message;
    }
}
