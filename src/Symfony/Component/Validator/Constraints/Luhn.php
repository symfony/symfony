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
 * Metadata for the LuhnValidator.
 *
 * @Annotation
 * @Target({"PROPERTY", "METHOD", "ANNOTATION"})
 *
 * @author Tim Nagel <t.nagel@infinite.net.au>
 * @author Greg Knapp http://gregk.me/2011/php-implementation-of-bank-card-luhn-algorithm/
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
#[\Attribute(\Attribute::TARGET_PROPERTY | \Attribute::TARGET_METHOD | \Attribute::IS_REPEATABLE)]
class Luhn extends Constraint
{
    public const INVALID_CHARACTERS_ERROR = 'dfad6d23-1b74-4374-929b-5cbb56fc0d9e';
    public const CHECKSUM_FAILED_ERROR = '4d760774-3f50-4cd5-a6d5-b10a3299d8d3';

    protected const ERROR_NAMES = [
        self::INVALID_CHARACTERS_ERROR => 'INVALID_CHARACTERS_ERROR',
        self::CHECKSUM_FAILED_ERROR => 'CHECKSUM_FAILED_ERROR',
    ];

    /**
     * @deprecated since Symfony 6.1, use const ERROR_NAMES instead
     */
    protected static $errorNames = self::ERROR_NAMES;

    public $message = 'Invalid card number.';

    public function __construct(
        array $options = null,
        string $message = null,
        array $groups = null,
        mixed $payload = null
    ) {
        parent::__construct($options, $groups, $payload);

        $this->message = $message ?? $this->message;
    }
}
