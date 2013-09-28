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
use Symfony\Component\Validator\Exception\InvalidOptionsException;
use Symfony\Component\Validator\Exception\MissingOptionsException;

/**
 * @Annotation
 *
 * @author Andrew Moore <me@andrewmoore.ca>
 *
 * @api
 */
class DateTimeRange extends Constraint
{
    public $minMessage = 'This value should be {{ limit }} or more.';
    public $maxMessage = 'This value should be {{ limit }} or less.';
    public $invalidMessage = 'This value is not a valid date.';
    public $min;
    public $max;
    public $timezone = 'UTC';

    public function __construct($options = null)
    {
        parent::__construct($options);

        if (null === $this->min && null === $this->max) {
            throw new MissingOptionsException(sprintf('Either option "min" or "max" must be given for constraint %s', __CLASS__), array('min', 'max'));
        }

        if (!$this->timezone instanceof \DateTimeZone) {
            try {
                $this->timezone = new \DateTimeZone($this->timezone);
            } catch (\Exception $ex) {
                // DateTimeZone throws a generic \Exception when format is invalid
                throw new InvalidOptionsException(sprintf(
                    'Option "timezone" must be a valid TimeZone for constraint %s',
                    __CLASS__
                ), array('timezone'), $ex);
            }
        }

        // Transform a min value specified in string (for annotation support) to a DateTime instance
        if (!($this->min instanceof \DateTime || null === $this->min)) {
            $minInvalid = true;
            $previousException = null;
            if (is_scalar($this->min) || (is_object($this->min) && method_exists($this->min, '__toString'))) {
                try {
                    $this->min = new \DateTime((string)$this->min, $this->timezone);
                    $minInvalid = false;
                } catch (\Exception $ex) {
                    // DateTime throws a generic \Exception when format is invalid
                    $previousException = $ex;
                }
            }

            if ($minInvalid) {
                throw new InvalidOptionsException(sprintf(
                    'Option "min" must be a DateTime or a string that is convertible to a DateTime for constraint %s',
                    __CLASS__
                ), array('min'), $previousException);
            }
        }

        // Transform a max value specified in string (for annotation support) to a DateTime instance
        if (!($this->max instanceof \DateTime || null === $this->max)) {
            $maxInvalid = true;
            $previousException = null;
            if (is_scalar($this->max) || (is_object($this->max) && method_exists($this->max, '__toString'))) {
                try {
                    $this->max = new \DateTime((string)$this->max, $this->timezone);
                    $maxInvalid = false;
                } catch (\Exception $ex) {
                    // DateTime throws a generic \Exception when format is invalid
                    $previousException = $ex;
                }
            }

            if ($maxInvalid) {
                throw new InvalidOptionsException(sprintf(
                    'Option "max" must be a DateTime or a string that is convertible to a DateTime for constraint %s',
                    __CLASS__
                ), array('max'), $previousException);
            }
        }
    }
}