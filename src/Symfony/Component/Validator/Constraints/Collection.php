<?php

namespace Symfony\Component\Validator\Constraints;

class Collection extends \Symfony\Component\Validator\Constraint
{
    public $fields;
    public $allowExtraFields = false;
    public $allowMissingFields = false;
    public $extraFieldsMessage = 'Symfony.Validator.Collection.extraFieldsMessage';
    public $missingFieldsMessage = 'Symfony.Validator.Collection.missingFieldsMessage';

    public function requiredOptions()
    {
        return array('fields');
    }
}