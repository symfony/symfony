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
class Count extends Constraint
{
    const ERROR_MIN = '4b57ee81-3a6b-4b43-93ae-69441571d39e';
    const ERROR_MAX = '9537c665-37eb-4515-a1fd-7beff83e6c1f';
    const ERROR_EXACT = 'd3625fef-ce0a-4332-a89e-408078ed6e58';

    public $minMessage = 'This collection should contain {{ limit }} element or more.|This collection should contain {{ limit }} elements or more.';
    public $maxMessage = 'This collection should contain {{ limit }} element or less.|This collection should contain {{ limit }} elements or less.';
    public $exactMessage = 'This collection should contain exactly {{ limit }} element.|This collection should contain exactly {{ limit }} elements.';
    public $min;
    public $max;

    public function __construct($options = null)
    {
        if (null !== $options && !is_array($options)) {
            $options = array(
                'min' => $options,
                'max' => $options,
            );
        }

        parent::__construct($options);

        if (null === $this->min && null === $this->max) {
            throw new MissingOptionsException(sprintf('Either option "min" or "max" must be given for constraint %s', __CLASS__), array('min', 'max'));
        }
    }
}
