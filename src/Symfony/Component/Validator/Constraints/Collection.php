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
 * @Annotation
 * @Target({"PROPERTY", "METHOD", "ANNOTATION"})
 *
 * @author Bernhard Schussek <bschussek@gmail.com>
 *
 * @api
 */
class Collection extends Composite
{
    public $fields = array();
    public $allowExtraFields = false;
    public $allowMissingFields = false;
    public $extraFieldsMessage = 'The fields {{ fields }} were not expected.';
    public $missingFieldsMessage = 'The fields {{ fields }} are missing.';

    /**
     * {@inheritdoc}
     */
    public function __construct($options = null)
    {
        // no known options set? $options is the fields array
        if (is_array($options)
            && !array_intersect(array_keys($options), array('groups', 'fields', 'allowExtraFields', 'allowMissingFields', 'extraFieldsMessage', 'missingFieldsMessage'))) {
            $options = array('fields' => $options);
        }

        parent::__construct($options);
    }

    /**
     * {@inheritdoc}
     */
    protected function initializeNestedConstraints()
    {
        parent::initializeNestedConstraints();

        if (!is_array($this->fields)) {
            throw new ConstraintDefinitionException(sprintf('The option "fields" is expected to be an array in constraint %s', __CLASS__));
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
        }
    }

    public function getRequiredOptions()
    {
        return array('fields');
    }

    protected function getCompositeOption()
    {
        return 'fields';
    }
}
