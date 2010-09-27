<?php

namespace Symfony\Component\Validator\Constraints;

class Collection extends \Symfony\Component\Validator\Constraint
{
    public $fields;
    public $allowExtraFields = false;
    public $allowMissingFields = false;
    public $extraFieldsMessage = 'The fields {{ fields }} were not expected';
    public $missingFieldsMessage = 'The fields {{ fields }} are missing';

    public function requiredOptions()
    {
        return array('fields');
    }
}