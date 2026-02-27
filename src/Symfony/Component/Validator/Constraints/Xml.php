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
use Symfony\Component\Validator\Exception\InvalidArgumentException;
use Symfony\Component\Validator\Exception\LogicException;

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

    public function __construct(
        public string $formatMessage = 'This value is not valid XML.',
        public string $schemaMessage = 'This value is not conform to the expected XSD schema.',
        public ?string $schemaPath = null,
        public int $schemaFlags = 0,
        ?array $groups = null,
        mixed $payload = null,
    ) {
        if (!\extension_loaded('simplexml')) {
            throw new LogicException('The "simplexml" extension is required to use the Xml constraint.');
        }
        if ($this->schemaPath && !\extension_loaded('dom')) {
            throw new LogicException('The "dom" extension is required to use the Xml constraint with schema validation.');
        }
        parent::__construct(null, $groups, $payload);

        if (null !== $this->schemaPath && !is_readable($this->schemaPath)) {
            throw new InvalidArgumentException(\sprintf('The XSD schema file "%s" does not exist or is unreadable.', $this->schemaPath));
        }
    }
}
