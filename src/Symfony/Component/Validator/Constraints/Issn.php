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
 * Validates that a value is a valid International Standard Serial Number (ISSN).
 *
 * @see https://en.wikipedia.org/wiki/ISSN
 *
 * @author Antonio J. García Lagar <aj@garcialagar.es>
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
#[\Attribute(\Attribute::TARGET_PROPERTY | \Attribute::TARGET_METHOD | \Attribute::IS_REPEATABLE)]
class Issn extends Constraint
{
    public const TOO_SHORT_ERROR = '6a20dd3d-f463-4460-8e7b-18a1b98abbfb';
    public const TOO_LONG_ERROR = '37cef893-5871-464e-8b12-7fb79324833c';
    public const MISSING_HYPHEN_ERROR = '2983286f-8134-4693-957a-1ec4ef887b15';
    public const INVALID_CHARACTERS_ERROR = 'a663d266-37c2-4ece-a914-ae891940c588';
    public const INVALID_CASE_ERROR = '7b6dd393-7523-4a6c-b84d-72b91bba5e1a';
    public const CHECKSUM_FAILED_ERROR = 'b0f92dbc-667c-48de-b526-ad9586d43e85';

    protected const ERROR_NAMES = [
        self::TOO_SHORT_ERROR => 'TOO_SHORT_ERROR',
        self::TOO_LONG_ERROR => 'TOO_LONG_ERROR',
        self::MISSING_HYPHEN_ERROR => 'MISSING_HYPHEN_ERROR',
        self::INVALID_CHARACTERS_ERROR => 'INVALID_CHARACTERS_ERROR',
        self::INVALID_CASE_ERROR => 'INVALID_CASE_ERROR',
        self::CHECKSUM_FAILED_ERROR => 'CHECKSUM_FAILED_ERROR',
    ];

    public string $message = 'This value is not a valid ISSN.';
    public bool $caseSensitive = false;
    public bool $requireHyphen = false;

    /**
     * @param array<string,mixed>|null $options
     * @param bool|null                $caseSensitive Whether to allow the value to end with a lowercase character (defaults to false)
     * @param bool|null                $requireHyphen Whether to require a hyphenated ISSN value (defaults to false)
     * @param string[]|null            $groups
     */
    #[HasNamedArguments]
    public function __construct(
        ?array $options = null,
        ?string $message = null,
        ?bool $caseSensitive = null,
        ?bool $requireHyphen = null,
        ?array $groups = null,
        mixed $payload = null,
    ) {
        if (\is_array($options)) {
            trigger_deprecation('symfony/validator', '7.3', 'Passing an array of options to configure the "%s" constraint is deprecated, use named arguments instead.', static::class);
        }

        parent::__construct($options, $groups, $payload);

        $this->message = $message ?? $this->message;
        $this->caseSensitive = $caseSensitive ?? $this->caseSensitive;
        $this->requireHyphen = $requireHyphen ?? $this->requireHyphen;
    }
}
