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

use Symfony\Component\Validator\Exception\ConstraintDefinitionException;

class Valid extends \Symfony\Component\Validator\Constraint
{
    /**
     * This constraint does not accept any options
     *
     * @param  mixed $options           Unsupported argument!
     *
     * @throws InvalidOptionsException  When the parameter $options is not NULL
     */
    public function __construct($options = null)
    {
        if ($options !== null && count($options) > 0) {
            throw new ConstraintDefinitionException('The constraint Valid does not accept any options');
        }
    }
}