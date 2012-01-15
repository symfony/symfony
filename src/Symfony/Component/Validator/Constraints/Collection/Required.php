<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Validator\Constraints\Collection;

use Symfony\Component\Validator\Constraint;

/**
 * @Annotation
 */
class Required extends Constraint
{
    public $constraints = array();

    public function getDefaultOption()
    {
        return 'constraints';
    }
}
