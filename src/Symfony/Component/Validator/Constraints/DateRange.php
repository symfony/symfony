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

use Symfony\Component\Validator\Constraints\Range;

/**
 * @Annotation
 *
 * @api
 *
 * @author Alexander Volochnev <admin@toplimit.ru>
 */
class DateRange extends Range
{
    public $format         = "d/m/Y";
    public $minMessage     = 'This date should be {{ limit }} or later.';
    public $maxMessage     = 'This date should be {{ limit }} or earlier.';
    public $invalidMessage = 'This value should be a valid date.';

    public function __construct($options = null)
    {
        parent::__construct($options);

        if (isset($options['format']) && null !== $options['format']) {
            $this->format = $options['format'];
        }

        if (null !== $this->min) {
            $this->min = \DateTime::createFromFormat($this->format, $this->min);
        }

        if (null !== $this->max) {
            $this->max = \DateTime::createFromFormat($this->format, $this->max);
        }
    }
}
