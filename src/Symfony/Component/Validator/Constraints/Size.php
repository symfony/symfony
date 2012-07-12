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
 * @api
 *
 * @deprecated Deprecated since version 2.1, to be removed in 2.3.
 */
class Size extends Range
{
    /**
     * {@inheritDoc}
     */
    public function validatedBy()
    {
        return get_parent_class($this).'Validator';
    }
}
