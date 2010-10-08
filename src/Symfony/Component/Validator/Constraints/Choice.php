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

class Choice extends \Symfony\Component\Validator\Constraint
{
    public $choices;
    public $callback;
    public $multiple = false;
    public $min = null;
    public $max = null;
    public $message = 'This value should be one of the given choices';
    public $minMessage = 'You should select at least {{ limit }} choices';
    public $maxMessage = 'You should select at most {{ limit }} choices';

    /**
     * {@inheritDoc}
     */
    public function defaultOption()
    {
        return 'choices';
    }
}
