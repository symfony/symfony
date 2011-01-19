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
        if (null !== $options && count($options) > 0) {
            throw new ConstraintDefinitionException('The constraint Valid does not accept any options');
        }
    }

    /**
     * {@inheritDoc}
     */
    public function targets()
    {
        return self::PROPERTY_CONSTRAINT;
    }
}