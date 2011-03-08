<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Form;

use Symfony\Component\Form\ValueTransformer\ReversedTransformer;
use Symfony\Component\Form\ValueTransformer\DateTimeToStringTransformer;
use Symfony\Component\Form\ValueTransformer\DateTimeToTimestampTransformer;
use Symfony\Component\Form\ValueTransformer\DateTimeToArrayTransformer;
use Symfony\Component\Form\ValueTransformer\ValueTransformerChain;

/**
 * A field for editing a date and a time simultaneously.
 *
 * Available options:
 *
 *  * date_widget:    How to render the date field ("input" or "choice"). Default: "choice".
 *  * time_widget:    How to render the time field ("input" or "choice"). Default: "choice".
 *  * type:           The type of the date stored on the object. Default: "datetime":
 *                    * datetime:   A DateTime object;
 *                    * string:     A raw string (e.g. 2011-05-01 12:30:00, Y-m-d H:i:s);
 *                    * timestamp:  A unix timestamp (e.g. 1304208000).
 *  * date_pattern:   The pattern for the select boxes when date "widget" is "choice".
 *                    You can use the placeholders "{{ year }}", "{{ month }}" and "{{ day }}".
 *                    Default: locale dependent.
 *  * with_seconds    Whether or not to create a field for seconds. Default: false.
 *
 *  * years:          An array of years for the year select tag.
 *  * months:         An array of months for the month select tag.
 *  * days:           An array of days for the day select tag.
 *  * hours:          An array of hours for the hour select tag.
 *  * minutes:        An array of minutes for the minute select tag.
 *  * seconds:        An array of seconds for the second select tag.
 *
 *  * date_format:    The date format type to use for displaying the date. Default: medium.
 *  * data_timezone:  The timezone of the data. Default: UTC.
 *  * user_timezone:  The timezone of the user entering a new value. Default: UTC.
 *
 * @author Bernhard Schussek <bernhard.schussek@symfony.com>
 */
class DateTimeField extends Form
{
    const DATETIME = 'datetime';
    const STRING = 'string';
    const TIMESTAMP = 'timestamp';

    protected static $types = array(
        self::DATETIME,
        self::STRING,
        self::TIMESTAMP,
    );

    protected static $dateFormats = array(
        DateField::FULL,
        DateField::LONG,
        DateField::MEDIUM,
        DateField::SHORT,
    );

    protected static $dateWidgets = array(
        DateField::CHOICE,
        DateField::INPUT,
    );

    protected static $timeWidgets = array(
        TimeField::CHOICE,
        TimeField::INPUT,
    );

    /**
     * {@inheritDoc}
     */
    public function __construct($key, array $options = array())
    {
        // Override parent option
        // \DateTime objects are never edited by reference, because
        // we treat them like value objects
        $this->addOption('by_reference', false);

        parent::__construct($key, $options);
    }

    protected function configure()
    {
        $this->addOption('date_widget', DateField::CHOICE, self::$dateWidgets);
        $this->addOption('time_widget', TimeField::CHOICE, self::$timeWidgets);
        $this->addOption('type', self::DATETIME, self::$types);
        $this->addOption('date_pattern');
        $this->addOption('with_seconds', false);

        $this->addOption('years', range(date('Y') - 5, date('Y') + 5));
        $this->addOption('months', range(1, 12));
        $this->addOption('days', range(1, 31));
        $this->addOption('hours', range(0, 23));
        $this->addOption('minutes', range(0, 59));
        $this->addOption('seconds', range(0, 59));

        $this->addOption('data_timezone', date_default_timezone_get());
        $this->addOption('user_timezone', date_default_timezone_get());
        $this->addOption('date_format', DateField::MEDIUM, self::$dateFormats);

        $this->add(new DateField('date', array(
            'type' => DateField::RAW,
            'widget' => $this->getOption('date_widget'),
            'format' => $this->getOption('date_format'),
            'data_timezone' => $this->getOption('user_timezone'),
            'user_timezone' => $this->getOption('user_timezone'),
            'years' => $this->getOption('years'),
            'months' => $this->getOption('months'),
            'days' => $this->getOption('days'),
            'pattern' => $this->getOption('date_pattern'),
        )));
        $this->add(new TimeField('time', array(
            'type' => TimeField::RAW,
            'widget' => $this->getOption('time_widget'),
            'data_timezone' => $this->getOption('user_timezone'),
            'user_timezone' => $this->getOption('user_timezone'),
            'with_seconds' => $this->getOption('with_seconds'),
            'hours' => $this->getOption('hours'),
            'minutes' => $this->getOption('minutes'),
            'seconds' => $this->getOption('seconds'),
        )));

        if ($this->getOption('type') == self::STRING) {
            $this->setNormalizationTransformer(new ReversedTransformer(
                new DateTimeToStringTransformer(array(
                    'input_timezone' => $this->getOption('data_timezone'),
                    'output_timezone' => $this->getOption('data_timezone'),
                ))
            ));
        } else if ($this->getOption('type') == self::TIMESTAMP) {
            $this->setNormalizationTransformer(new ReversedTransformer(
                new DateTimeToTimestampTransformer(array(
                    'input_timezone' => $this->getOption('data_timezone'),
                    'output_timezone' => $this->getOption('data_timezone'),
                ))
            ));
        }

        $this->setValueTransformer(new DateTimeToArrayTransformer(array(
            'input_timezone' => $this->getOption('data_timezone'),
            'output_timezone' => $this->getOption('user_timezone'),
        )));
    }

    /**
     * {@inheritDoc}
     */
    protected function transform($value)
    {
        $value = parent::transform($value);

        return array('date' => $value, 'time' => $value);
    }

    /**
     * {@inheritDoc}
     */
    protected function reverseTransform($value)
    {
        return parent::reverseTransform(array_merge($value['date'], $value['time']));
    }
}
