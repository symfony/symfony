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
class Length extends Constraint
{
    const ERROR = 'cca1a7c3-beed-44c8-a12c-dd94e6208631';
    const ERROR_MAX = '2127fa68-8220-4d66-bdbd-6c9e12c4e529';
    const ERROR_MIN = '7ec9200c-b68d-4aff-b29f-d546912ed243';

    public $maxMessage = 'This value is too long. It should have {{ limit }} character or less.|This value is too long. It should have {{ limit }} characters or less.';
    public $minMessage = 'This value is too short. It should have {{ limit }} character or more.|This value is too short. It should have {{ limit }} characters or more.';
    public $exactMessage = 'This value should have exactly {{ limit }} character.|This value should have exactly {{ limit }} characters.';
    public $max;
    public $min;
    public $charset = 'UTF-8';

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
