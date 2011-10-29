<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bridge\Doctrine\Validator\Constraints;

use Symfony\Component\Validator\Constraint;

/**
 * Constraint for the Unique Entity validator
 *
 * @Annotation
 * @author Benjamin Eberlei <kontakt@beberlei.de>
 */
class UniqueEntity extends Constraint
{
    public $message = 'This value is already used';
    public $em = null;
    public $fields = array();

    public function getRequiredOptions()
    {
        return array('fields');
    }

    /**
     * The validator must be defined as a service with this name.
     *
     * @return string
     */
    public function validatedBy()
    {
        return 'doctrine.orm.validator.unique';
    }

    /**
     * {@inheritDoc}
     */
    public function getTargets()
    {
        return self::CLASS_CONSTRAINT;
    }

    public function getDefaultOption()
    {
        return 'fields';
    }
}
