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
 * Validates that a value is a valid XML string.
 *
 * @author Mokhtar Tlili <tlili.mokhtar@gmail.com>
 */
#[\Attribute(\Attribute::TARGET_PROPERTY | \Attribute::TARGET_METHOD | \Attribute::IS_REPEATABLE)]
class Xml extends Constraint
{
    public const INVALID_XML_ERROR = '0355230a-97b8-49da-b8cd-985bf3345bcf';

    protected const ERROR_NAMES = [
        self::INVALID_XML_ERROR => 'INVALID_XML_ERROR',
    ];

    #[HasNamedArguments]
    public function __construct(
        public string $message = 'This value is not valid XML.',
        ?array $groups = null,
        mixed $payload = null,
    ) {
        parent::__construct(null, $groups, $payload);
    }
}
