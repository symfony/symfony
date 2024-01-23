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
 * @Annotation
 * @Target({"PROPERTY", "METHOD", "ANNOTATION"})
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

    /**
     * @deprecated since Symfony 6.1, use const ERROR_NAMES instead
     */
    protected static $errorNames = self::ERROR_NAMES;

    public $message = 'This value is not a valid ISSN.';
    public $caseSensitive = false;
    public $requireHyphen = false;

    public function __construct(
        ?array $options = null,
        ?string $message = null,
        ?bool $caseSensitive = null,
        ?bool $requireHyphen = null,
        ?array $groups = null,
        mixed $payload = null
    ) {
        parent::__construct($options, $groups, $payload);

        $this->message = $message ?? $this->message;
        $this->caseSensitive = $caseSensitive ?? $this->caseSensitive;
        $this->requireHyphen = $requireHyphen ?? $this->requireHyphen;
    }
}
