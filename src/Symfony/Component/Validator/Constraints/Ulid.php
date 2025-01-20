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
use Symfony\Component\Validator\Exception\ConstraintDefinitionException;

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
    public const TOO_LARGE_ERROR = 'df8cfb9a-ce6d-4a69-ae5a-eea7ab6f278b';
    public const INVALID_CHARACTERS_ERROR = 'e4155739-5135-4258-9c81-ae7b44b5311e';
    public const INVALID_FORMAT_ERROR = '34d5cdd7-5aac-4ba0-b9a2-b45e0bab3e2e';

    protected const ERROR_NAMES = [
        self::TOO_SHORT_ERROR => 'TOO_SHORT_ERROR',
        self::TOO_LONG_ERROR => 'TOO_LONG_ERROR',
        self::TOO_LARGE_ERROR => 'TOO_LARGE_ERROR',
        self::INVALID_CHARACTERS_ERROR => 'INVALID_CHARACTERS_ERROR',
        self::INVALID_FORMAT_ERROR => 'INVALID_FORMAT_ERROR',
    ];

    public const FORMAT_BASE_32 = 'base32';
    public const FORMAT_BASE_58 = 'base58';
    public const FORMAT_RFC_4122 = 'rfc4122';

    public string $message = 'This is not a valid ULID.';
    public string $format = self::FORMAT_BASE_32;

    /**
     * @param array<string,mixed>|null $options
     * @param string[]|null            $groups
     * @param self::FORMAT_*|null      $format
     */
    public function __construct(
        ?array $options = null,
        ?string $message = null,
        ?array $groups = null,
        mixed $payload = null,
        ?string $format = null,
    ) {
        parent::__construct($options, $groups, $payload);

        $this->message = $message ?? $this->message;
        $this->format = $format ?? $this->format;

        if (!\in_array($this->format, [self::FORMAT_BASE_32, self::FORMAT_BASE_58, self::FORMAT_RFC_4122], true)) {
            throw new ConstraintDefinitionException(\sprintf('The "%s" validation format is not supported.', $format));
        }
    }
}
