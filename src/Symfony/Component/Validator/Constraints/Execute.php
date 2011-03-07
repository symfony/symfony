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

class Execute extends \Symfony\Component\Validator\Constraint
{
    public $methods;

    /**
     * {@inheritDoc}
     */
    public function requiredOptions()
    {
        return array('methods');
    }

    /**
     * {@inheritDoc}
     */
    public function defaultOption()
    {
        return 'methods';
    }

    /**
     * {@inheritDoc}
     */
    public function targets()
    {
        return self::CLASS_CONSTRAINT;
    }
}
