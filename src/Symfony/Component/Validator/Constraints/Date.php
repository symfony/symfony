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
 * @author Bernhard Schussek <bschussek@gmail.com>
 *
 * @api
 */
class Date extends Constraint
{
    public $message               = 'This value is not a valid date.';
    public $messageBeforeDate     = 'The date should be before {{ before }}.';
    public $messageAfterDate      = 'The date should be after {{ after }}.';
    public $dateFormatMessages    = 'yyyy-MM-dd'; // see http://userguide.icu-project.org/formatparse/datetime
    public $dateFormatter;

    public $before;
    public $after;

    public function __construct($options = null)
    {
        if (isset($options['after']) && !$options['after'] instanceof \DateTime) {
            $options['after'] = new \DateTime($options['after']);
        }

        if (isset($options['before']) && !$options['before'] instanceof \DateTime) {
            $options['before'] = new \DateTime($options['before']);
        }

        if (isset($options['before']) && isset($options['after'])) {
            if ($options['before'] == $options['after']) {
                throw new InvalidOptionsException('The options "after" and "before" may not have the same value. ' . __CLASS__, array('after', 'before'));
            }
            if ($options['before'] < $options['after']) {
                throw new InvalidOptionsException('The value of "before" must be a date later than the value of "after". ' . __CLASS__, array('after', 'before'));
            }
        }

        if (isset($options['dateFormatter'])) {
            if (!$options['dateFormatter'] instanceof \IntlDateFormatter) {
                throw new InvalidOptionsException('The option "dateFormatter" must be an instance of \IntlDateFormatter.' . __CLASS__, array('dateFormatter'));
            }
        } else {
            $options['dateFormatter']  = new \IntlDateFormatter('en_US', \IntlDateFormatter::SHORT, \IntlDateFormatter::NONE);
        }

        if (isset($options['dateFormatMessages'])) {
            if (!$options['dateFormatter']->setPattern($options['dateFormatMessages'])) {
                throw new InvalidOptionsException('The value of the option "dateFormatMessages" is invalid. ' . __CLASS__, array('dateFormatMessages'));
            }
        } else {
            $options['dateFormatter']->setPattern($this->dateFormatMessages);
        }

        parent::__construct($options);
    }
}