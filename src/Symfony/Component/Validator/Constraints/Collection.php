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
use Symfony\Component\Validator\Constraints\Collection\Required;
use Symfony\Component\Validator\Constraints\Collection\Optional;
use Symfony\Component\Validator\Exception\ConstraintDefinitionException;

/**
 * @Annotation
 *
 * @api
 */
class Collection extends Constraint
{
    public $fields;
    public $allowExtraFields = false;
    public $allowMissingFields = false;
    public $extraFieldsMessage = 'This field was not expected.';
    public $missingFieldsMessage = 'This field is missing.';

    /**
     * {@inheritDoc}
     */
    public function __construct($options = null)
    {
        // no known options set? $options is the fields array
        if (is_array($options)
            && !array_intersect(array_keys($options), array('groups', 'fields', 'allowExtraFields', 'allowMissingFields', 'extraFieldsMessage', 'missingFieldsMessage'))) {
            $options = array('fields' => $options);
        }

        parent::__construct($options);

        if (!is_array($this->fields)) {
            throw new ConstraintDefinitionException('The option "fields" is expected to be an array in constraint ' . __CLASS__);
        }

        foreach ($this->fields as $fieldName => $field) {
            // the XmlFileLoader and YamlFileLoader pass the field Optional
            // and Required constraint as an array with exactly one element
            if (is_array($field) && count($field) == 1) {
                $this->fields[$fieldName] = $field = $field[0];
            }

            if (!$field instanceof Optional && !$field instanceof Required) {
                $this->fields[$fieldName] = $field = new Required($field);
            }

            if (!is_array($field->constraints)) {
                $field->constraints = array($field->constraints);
            }

            foreach ($field->constraints as $constraint) {
                if (!$constraint instanceof Constraint) {
                    throw new ConstraintDefinitionException('The value ' . $constraint . ' of the field ' . $fieldName . ' is not an instance of Constraint in constraint ' . __CLASS__);
                }

                if ($constraint instanceof Valid) {
                    throw new ConstraintDefinitionException('The constraint Valid cannot be nested inside constraint ' . __CLASS__ . '. You can only declare the Valid constraint directly on a field or method.');
                }
            }
        }
    }

    public function getRequiredOptions()
    {
        return array('fields');
    }
}
