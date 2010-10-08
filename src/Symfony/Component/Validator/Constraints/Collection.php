<?php

namespace Symfony\Component\Validator\Constraints;

/*
 * This file is part of the Symfony framework.
 *
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

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