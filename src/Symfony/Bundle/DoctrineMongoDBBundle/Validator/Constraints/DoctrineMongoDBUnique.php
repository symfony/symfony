<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\DoctrineMongoDBBundle\Validator\Constraints;

use Symfony\Component\Validator\Constraint;

/**
 * Doctrine MongoDB ODM unique value constraint.
 *
 * @author Bulat Shakirzyanov <bulat@theopenskyproject.com>
 */
class DoctrineMongoDBUnique extends Constraint
{
    public $message = 'The value for {{ property }} already exists.';
    public $path;

    public function defaultOption()
    {
        return 'path';
    }

    public function requiredOptions()
    {
        return array('path');
    }

    public function validatedBy()
    {
        return 'doctrine_odm.mongodb.validator.unique';
    }

    public function targets()
    {
        return Constraint::CLASS_CONSTRAINT;
    }
}
