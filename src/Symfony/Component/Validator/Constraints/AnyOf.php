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
 * @author Oleg Stepura <github@oleg.stepura.com>
 */
class AnyOf extends Constraint
{
    public $message = 'Value "{{ value }}" did not pass validation against any of constraints in the list [{{ constraints }}]';

    /**
     * @var array
     */
    public $constraints = array();

    /**
     * @return string
     */
    public function getDefaultOption()
    {
        return 'constraints';
    }

    /**
     * @return array
     */
    public function getRequiredOptions()
    {
        return array('constraints');
    }
}
