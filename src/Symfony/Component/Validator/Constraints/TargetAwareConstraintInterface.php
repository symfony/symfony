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

/**
 * This interface is to be implemented by constraints that need to be
 * aware of the exact target they have been declared on.
 *
 * This interface only makes sense for class constraints. Which could be
 * attached to multiple classes because of class inheritance.
 *
 * @since 3.1
 *
 * @author Mathieu Lemoine <mlemoine@mlemoine.name>
 */
interface TargetAwareConstraintInterface
{
    /*
     * Since constraints are implemented using public properties,
     * the interface is intended as a tag and declare no method.
     */
}
