<?php

/**
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bridge\Twig\Validator\Constraint;

use Symfony\Component\Validator\Constraint;

/**
 * Assert that a template contain not syntax error.
 *
 * @Annotation
 *
 * @author Gary PEGEOT <garypegeot@gmail.com>
 */
class IsValidTemplate extends Constraint
{
    /**
     * Default error message.
     *
     * @var string
     */
    public $message = 'Error at line {{ line }}: "{{ error }}".';
}
