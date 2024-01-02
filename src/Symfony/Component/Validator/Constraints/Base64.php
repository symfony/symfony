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
 * @author Alexandre Daubois <alex.daubois@gmail.com>
 */
#[\Attribute(\Attribute::TARGET_PROPERTY | \Attribute::TARGET_METHOD | \Attribute::IS_REPEATABLE)]
final class Base64 extends Constraint
{
    public const INVALID_STRING_ERROR = '5699d0ba-3cea-4676-9aab-d3303ebd6934';
    public const MISSING_DATA_URI_ERROR = '9380f49b-582a-49ce-a0b9-e68ff009e80f';

    protected const ERROR_NAMES = [
        self::INVALID_STRING_ERROR => 'INVALID_STRING_ERROR',
        self::MISSING_DATA_URI_ERROR => 'MISSING_DATA_URI_ERROR',
    ];

    public string $messageInvalidString = 'The given string is not a valid Base64 encoded string.';
    public string $messageMissingDataUri = 'The given string is missing a data URI.';

    public function __construct(public bool $requiresDataUri = false, public bool $urlEncoded = false, string $messageInvalidString = null, string $messageMissingDataUri = null, array $groups = null, mixed $payload = null, array $options = null)
    {
        parent::__construct($options, $groups, $payload);

        $this->messageInvalidString = $messageInvalidString ?? $this->messageInvalidString;
        $this->messageMissingDataUri = $messageMissingDataUri ?? $this->messageMissingDataUri;
    }
}
