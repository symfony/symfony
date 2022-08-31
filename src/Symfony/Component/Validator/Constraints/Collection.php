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

use Symfony\Component\Validator\Exception\ConstraintDefinitionException;

/**
 * @Annotation
 * @Target({"PROPERTY", "METHOD", "ANNOTATION"})
 *
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
#[\Attribute(\Attribute::TARGET_PROPERTY | \Attribute::TARGET_METHOD | \Attribute::IS_REPEATABLE)]
class Collection extends Composite
{
    public const MISSING_FIELD_ERROR = '2fa2158c-2a7f-484b-98aa-975522539ff8';
    public const NO_SUCH_FIELD_ERROR = '7703c766-b5d5-4cef-ace7-ae0dd82304e9';

    protected const ERROR_NAMES = [
        self::MISSING_FIELD_ERROR => 'MISSING_FIELD_ERROR',
        self::NO_SUCH_FIELD_ERROR => 'NO_SUCH_FIELD_ERROR',
    ];

    /**
     * @deprecated since Symfony 6.1, use const ERROR_NAMES instead
     */
    protected static $errorNames = self::ERROR_NAMES;

    public $fields = [];
    public $allowExtraFields = false;
    public $allowMissingFields = false;
    public $extraFieldsMessage = 'This field was not expected.';
    public $missingFieldsMessage = 'This field is missing.';

    public function __construct(mixed $fields = null, array $groups = null, mixed $payload = null, bool $allowExtraFields = null, bool $allowMissingFields = null, string $extraFieldsMessage = null, string $missingFieldsMessage = null)
    {
        // no known options set? $fields is the fields array
        if (\is_array($fields)
            && !array_intersect(array_keys($fields), ['groups', 'fields', 'allowExtraFields', 'allowMissingFields', 'extraFieldsMessage', 'missingFieldsMessage'])) {
            $fields = ['fields' => $fields];
        }

        parent::__construct($fields, $groups, $payload);

        $this->allowExtraFields = $allowExtraFields ?? $this->allowExtraFields;
        $this->allowMissingFields = $allowMissingFields ?? $this->allowMissingFields;
        $this->extraFieldsMessage = $extraFieldsMessage ?? $this->extraFieldsMessage;
        $this->missingFieldsMessage = $missingFieldsMessage ?? $this->missingFieldsMessage;
    }

    protected function initializeNestedConstraints()
    {
        parent::initializeNestedConstraints();

        if (!\is_array($this->fields)) {
            throw new ConstraintDefinitionException(sprintf('The option "fields" is expected to be an array in constraint "%s".', __CLASS__));
        }

        foreach ($this->fields as $fieldName => $field) {
            // the XmlFileLoader and YamlFileLoader pass the field Optional
            // and Required constraint as an array with exactly one element
            if (\is_array($field) && 1 == \count($field)) {
                $this->fields[$fieldName] = $field = $field[0];
            }

            if (!$field instanceof Optional && !$field instanceof Required) {
                $this->fields[$fieldName] = new Required($field);
            }
        }
    }

    public function getRequiredOptions(): array
    {
        return ['fields'];
    }

    protected function getCompositeOption(): string
    {
        return 'fields';
    }
}
