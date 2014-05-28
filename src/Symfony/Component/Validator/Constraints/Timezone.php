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
 * @Target({"PROPERTY", "METHOD", "ANNOTATION"})
 *
 * @author Mickaël Andrieu <mickael.andrieu@sensiolabs.com>
 *
 * @api
 */
class Timezone extends Constraint
{
    public $message = 'The value "{{ value }}" is not a valid timezone';
}
