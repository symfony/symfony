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

use Symfony\Component\Validator\Constraint;

/**
 * @Annotation
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
class Condition extends Constraint
{
    public $message = 'This value is not valid.';
    public $condition;

    /**
     * {@inheritDoc}
     */
    public function getDefaultOption()
    {
        return 'condition';
    }

    /**
     * {@inheritDoc}
     */
    public function getRequiredOptions()
    {
        return array('condition');
    }
    /**
     * {@inheritDoc}
     */
    public function getTargets()
    {
        return self::CLASS_CONSTRAINT;
    }
}
