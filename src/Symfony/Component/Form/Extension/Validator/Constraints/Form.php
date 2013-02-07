<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Form\Extension\Validator\Constraints;

use Symfony\Component\Validator\Constraint;

/**
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class Form extends Constraint
{
    /**
     * Violation code marking an invalid form.
     */
    const ERR_INVALID = 1;

    /**
     * {@inheritdoc}
     */
    public function getTargets()
    {
        return self::CLASS_CONSTRAINT;
    }
}
