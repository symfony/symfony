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
 *
 * @api
 */
class Valid extends Constraint
{
    public $traverse = true;

    public $deep = false;

    public function __construct($options = null)
    {
        if (is_array($options) && array_key_exists('groups', $options)) {
            throw new ConstraintDefinitionException('The option "groups" is not supported by the constraint ' . __CLASS__);
        }

        parent::__construct($options);
    }
}
