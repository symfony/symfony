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

class Type extends \Symfony\Component\Validator\Constraint
{
    public $message = 'This value should be of type {{ type }}';
    public $type;

    /**
     * {@inheritDoc}
     */
    public function getDefaultOption()
    {
        return 'type';
    }

    /**
     * {@inheritDoc}
     */
    public function getRequiredOptions()
    {
        return array('type');
    }

    /**
     * {@inheritDoc}
     */
    public function getTargets()
    {
        return self::PROPERTY_CONSTRAINT;
    }
}
