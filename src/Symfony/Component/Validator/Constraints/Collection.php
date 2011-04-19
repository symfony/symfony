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

class Collection extends \Symfony\Component\Validator\Constraint
{
    public $fields;
    public $allowExtraFields = false;
    public $allowMissingFields = false;
    public $extraFieldsMessage = 'The fields {{ fields }} were not expected';
    public $missingFieldsMessage = 'The fields {{ fields }} are missing';

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
    }

    public function getRequiredOptions()
    {
        return array('fields');
    }

    /**
     * {@inheritDoc}
     */
    public function getTargets()
    {
        return self::PROPERTY_CONSTRAINT;
    }
}