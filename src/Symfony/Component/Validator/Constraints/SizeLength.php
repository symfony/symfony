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
class SizeLength extends Constraint
{
    public $minMessage   = 'This value is too short. It should have {{ limit }} characters or more.';
    public $maxMessage   = 'This value is too long. It should have {{ limit }} characters or less.';
    public $exactMessage = 'This value should have exactly {{ limit }} characters.';
    public $min;
    public $max;
    public $charset = 'UTF-8';

    /**
     * {@inheritDoc}
     */
    public function getRequiredOptions()
    {
        return array('min', 'max');
    }
}
