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

class ExactLength extends \Symfony\Component\Validator\Constraint
{
    public $message = 'This value should have exactly {{ length }} characters';
    public $length;
    public $charset = 'UTF-8';

    /**
     * {@inheritDoc}
     */
    public function getDefaultOption()
    {
        return 'length';
    }

    /**
     * {@inheritDoc}
     */
    public function getRequiredOptions()
    {
        return array('length');
    }

    /**
     * {@inheritDoc}
     */
    public function getTargets()
    {
        return self::PROPERTY_CONSTRAINT;
    }
}