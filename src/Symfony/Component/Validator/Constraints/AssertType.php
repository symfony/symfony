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

class AssertType extends \Symfony\Component\Validator\Constraint
{
    public $message = 'This value should be of type {{ type }}';
    public $type;

    /**
     * {@inheritDoc}
     */
    public function defaultOption()
    {
        return 'type';
    }

    /**
     * {@inheritDoc}
     */
    public function requiredOptions()
    {
        return array('type');
    }
}
