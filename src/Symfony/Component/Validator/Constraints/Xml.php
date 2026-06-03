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
    public const INVALID_SCHEMA_ERROR = '1c2ab9d4-7d20-4f0c-83a3-3f2e6b34d7e1';
    public const TOO_LARGE_ERROR = '6f1aa7a3-7d4d-4f1b-9c45-1f9c2d8f3b9b';

    protected const ERROR_NAMES = [
        self::INVALID_XML_ERROR => 'INVALID_XML_ERROR',
        self::INVALID_SCHEMA_ERROR => 'INVALID_SCHEMA_ERROR',
        self::TOO_LARGE_ERROR => 'TOO_LARGE_ERROR',
    ];

    public function __construct(
        public string $formatMessage = 'This value is not valid XML.',
        public string $schemaMessage = 'This value does not conform to the expected XSD schema.',
        public string $tooLargeMessage = 'This XML payload is too large ({{ size }} bytes): it exceeds the limit of {{ limit }} bytes.',
        public ?string $schemaPath = null,
        public int $schemaFlags = 0,
        public int $maxSize = 5 * 1024 * 1024,
        ?array $groups = null,
        mixed $payload = null,
    ) {
        if (!\extension_loaded('simplexml')) {
            throw new LogicException('The "simplexml" extension is required to use the Xml constraint.');
        }
        if ($this->schemaPath && !\extension_loaded('dom')) {
            throw new LogicException('The "dom" extension is required to use the Xml constraint with schema validation.');
        }
        if ($schemaFlags & \LIBXML_NOENT) {
            throw new ConstraintDefinitionException('The "schemaFlags" option must not include LIBXML_NOENT as it enables XML external entity substitution.');
        }
        if ($schemaFlags & \LIBXML_DTDLOAD) {
            throw new ConstraintDefinitionException('The "schemaFlags" option must not include LIBXML_DTDLOAD as it enables loading of external DTDs.');
        }
        if ($maxSize < 1) {
            throw new ConstraintDefinitionException('The "maxSize" option must be a positive integer.');
        }
        $this->schemaFlags |= \LIBXML_NONET;

        parent::__construct(null, $groups, $payload);

        if (null !== $this->schemaPath && !is_readable($this->schemaPath)) {
            throw new InvalidArgumentException(\sprintf('The XSD schema file "%s" does not exist or is unreadable.', $this->schemaPath));
        }
    }
}
