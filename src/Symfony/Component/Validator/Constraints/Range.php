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
use Symfony\Component\Validator\Exception\MissingOptionsException;

/**
 * @Annotation
 *
 * @author Bernhard Schussek <bschussek@gmail.com>
 *
 * @api
 */
class Range extends Constraint
{
    const ERROR = 'bc619de0-7f99-4d29-b5cb-f93bce9f6274';
    const ERROR_MIN = '0be45eb8-3d65-452b-9cbe-3b398c5c08c7';
    const ERROR_MAX = 'ac98b917-6b0a-4bea-8bac-e937064d3b42';

    public $minMessage = 'This value should be {{ limit }} or more.';
    public $maxMessage = 'This value should be {{ limit }} or less.';
    public $invalidMessage = 'This value should be a valid number.';
    public $min;
    public $max;

    public function __construct($options = null)
    {
        parent::__construct($options);

        if (null === $this->min && null === $this->max) {
            throw new MissingOptionsException(sprintf('Either option "min" or "max" must be given for constraint %s', __CLASS__), array('min', 'max'));
        }
    }
}
