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

class All extends \Symfony\Component\Validator\Constraint
{
    public $constraints = array();

    public function defaultOption()
    {
        return 'constraints';
    }

    public function requiredOptions()
    {
        return array('constraints');
    }
}