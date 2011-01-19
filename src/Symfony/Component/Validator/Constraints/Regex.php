<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Validator\Constraints;

class Regex extends \Symfony\Component\Validator\Constraint
{
    public $message = 'This value is not valid';
    public $pattern;
    public $match = true;

    /**
     * {@inheritDoc}
     */
    public function defaultOption()
    {
        return 'pattern';
    }

    /**
     * {@inheritDoc}
     */
    public function requiredOptions()
    {
        return array('pattern');
    }

    /**
     * {@inheritDoc}
     */
    public function targets()
    {
        return self::PROPERTY_CONSTRAINT;
    }
}