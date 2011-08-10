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
 */
class Regex extends Constraint
{
    public $message = 'This value is not valid';
    public $pattern;
    public $match = true;

    /**
     * {@inheritDoc}
     */
    public function getDefaultOption()
    {
        return 'pattern';
    }

    /**
     * {@inheritDoc}
     */
    public function getRequiredOptions()
    {
        return array('pattern');
    }
}
