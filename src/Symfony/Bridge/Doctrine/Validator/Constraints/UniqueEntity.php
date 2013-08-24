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
 *
 * @since v2.0.0
 */
class UniqueEntity extends Constraint
{
    public $message = 'This value is already used.';
    public $service = 'doctrine.orm.validator.unique';
    public $em = null;
    public $repositoryMethod = 'findBy';
    public $fields = array();
    public $errorPath = null;
    public $ignoreNull = true;

    /**
     * @since v2.0.0
     */
    public function getRequiredOptions()
    {
        return array('fields');
    }

    /**
     * The validator must be defined as a service with this name.
     *
     * @return string
     *
     * @since v2.0.0
     */
    public function validatedBy()
    {
        return $this->service;
    }

    /**
     * {@inheritDoc}
     *
     * @since v2.0.0
     */
    public function getTargets()
    {
        return self::CLASS_CONSTRAINT;
    }

    /**
     * @since v2.0.0
     */
    public function getDefaultOption()
    {
        return 'fields';
    }
}
