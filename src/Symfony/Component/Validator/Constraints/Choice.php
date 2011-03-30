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
    public function getDefaultOption()
    {
        return 'choices';
    }

    /**
     * {@inheritDoc}
     */
    public function getTargets()
    {
        return self::PROPERTY_CONSTRAINT;
    }
}
