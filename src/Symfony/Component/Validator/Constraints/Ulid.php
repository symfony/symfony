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
 * Validates that a value is a valid Universally Unique Lexicographically Sortable Identifier (ULID).
 *
 * @see https://github.com/ulid/spec
 *
 * @author Laurent Clouet <laurent35240@gmail.com>
 */
#[\Attribute(\Attribute::TARGET_PROPERTY | \Attribute::TARGET_METHOD | \Attribute::IS_REPEATABLE)]
class Ulid extends Constraint
{
    public const TOO_SHORT_ERROR = '7b44804e-37d5-4df4-9bdd-b738d4a45bb4';
    public const TOO_LONG_ERROR = '9608249f-6da1-4d53-889e-9864b58c4d37';
    public const INVALID_CHARACTERS_ERROR = 'e4155739-5135-4258-9c81-ae7b44b5311e';
    public const TOO_LARGE_ERROR = 'df8cfb9a-ce6d-4a69-ae5a-eea7ab6f278b';

    protected const ERROR_NAMES = [
        self::TOO_SHORT_ERROR => 'TOO_SHORT_ERROR',
        self::TOO_LONG_ERROR => 'TOO_LONG_ERROR',
        self::INVALID_CHARACTERS_ERROR => 'INVALID_CHARACTERS_ERROR',
        self::TOO_LARGE_ERROR => 'TOO_LARGE_ERROR',
    ];

    public string $message = 'This is not a valid ULID.';

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
        parent::__construct($options, $groups, $payload);

        $this->message = $message ?? $this->message;
    }
}
