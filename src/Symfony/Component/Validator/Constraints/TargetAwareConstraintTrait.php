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
 * This trait is intended as a helper for implementing TargetAwareConstraintInterface.
 *
 * Since the interface interface only makes sense for class constraints,
 * the default targets is set to class constraint.
 *
 * @since 3.1
 *
 * @author Mathieu Lemoine <mlemoine@mlemoine.name>
 */
trait TargetAwareConstraintTrait
{
    public $target;

    /**
     * {@inheritdoc}
     */
    public function getTargets()
    {
        return Constraint::CLASS_CONSTRAINT;
    }
}
