<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Form;

use Symfony\Component\Form\ValueTransformer\ReversedTransformer;
use Symfony\Component\Form\ValueTransformer\DateTimeToStringTransformer;
use Symfony\Component\Form\ValueTransformer\DateTimeToTimestampTransformer;
use Symfony\Component\Form\ValueTransformer\ValueTransformerChain;
use Symfony\Component\Form\ValueTransformer\DateTimeToLocalizedStringTransformer;
use Symfony\Component\Form\ValueTransformer\DateTimeToArrayTransformer;
use Symfony\Component\Form\ChoiceList\PaddedChoiceList;
use Symfony\Component\Form\ChoiceList\MonthChoiceList;

/**
 * Represents a date field.
 *
 * Available options:
 *
 *  * widget:         How to render the field ("input" or "choice"). Default: "choice".
 *  * type:           The type of the date stored on the object. Default: "datetime":
 *                    * datetime:   A DateTime object;
 *                    * string:     A raw string (e.g. 2011-05-01, Y-m-d);
 *                    * timestamp:  A unix timestamp (e.g. 1304208000);
 *                    * raw:        A year, month, day array.
 *  * pattern:        The pattern for the select boxes when "widget" is "choice".
 *                    You can use the placeholders "{{ year }}", "{{ month }}" and "{{ day }}".
 *                    Default: locale dependent.
 *
 *  * years:          An array of years for the year select tag.
 *  * months:         An array of months for the month select tag.
 *  * days:           An array of days for the day select tag.
 *
 *  * format:         The date format type to use for displaying the data. Default: medium.
 *  * data_timezone:  The timezone of the data. Default: server timezone.
 *  * user_timezone:  The timezone of the user entering a new value. Default: server timezone.
 *
 */
class DateField extends HybridField
{
    const FULL = 'full';
    const LONG = 'long';
    const MEDIUM = 'medium';
    const SHORT = 'short';

    const DATETIME = 'datetime';
    const STRING = 'string';
    const TIMESTAMP = 'timestamp';
    const RAW = 'raw';

    const INPUT = 'input';
    const CHOICE = 'choice';

    protected static $formats = array(
        self::FULL,
        self::LONG,
        self::MEDIUM,
        self::SHORT,
    );

    protected static $intlFormats = array(
        self::FULL => \IntlDateFormatter::FULL,
        self::LONG => \IntlDateFormatter::LONG,
        self::MEDIUM => \IntlDateFormatter::MEDIUM,
        self::SHORT => \IntlDateFormatter::SHORT,
    );

    protected static $widgets = array(
        self::INPUT,
        self::CHOICE,
    );

    protected static $types = array(
        self::DATETIME,
        self::STRING,
        self::TIMESTAMP,
        self::RAW,
    );

    /**
     * The ICU formatter instance
     * @var \IntlDateFormatter
     */
    protected $formatter;

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
        $this->addOption('widget', self::CHOICE, self::$widgets);
        $this->addOption('type', self::DATETIME, self::$types);
        $this->addOption('pattern');

        $this->addOption('years', range(date('Y') - 5, date('Y') + 5));
        $this->addOption('months', range(1, 12));
        $this->addOption('days', range(1, 31));

        $this->addOption('format', self::MEDIUM, self::$formats);
        $this->addOption('data_timezone', date_default_timezone_get());
        $this->addOption('user_timezone', date_default_timezone_get());

        $this->formatter = new \IntlDateFormatter(
            \Locale::getDefault(),
            self::$intlFormats[$this->getOption('format')],
            \IntlDateFormatter::NONE
        );

        if ($this->getOption('type') === self::STRING) {
            $this->setNormalizationTransformer(new ReversedTransformer(
                new DateTimeToStringTransformer(array(
                    'input_timezone' => $this->getOption('data_timezone'),
                    'output_timezone' => $this->getOption('data_timezone'),
                    'format' => 'Y-m-d',
                ))
            ));
        } else if ($this->getOption('type') === self::TIMESTAMP) {
            $this->setNormalizationTransformer(new ReversedTransformer(
                new DateTimeToTimestampTransformer(array(
                    'output_timezone' => $this->getOption('data_timezone'),
                    'input_timezone' => $this->getOption('data_timezone'),
                ))
            ));
        } else if ($this->getOption('type') === self::RAW) {
            $this->setNormalizationTransformer(new ReversedTransformer(
                new DateTimeToArrayTransformer(array(
                    'input_timezone' => $this->getOption('data_timezone'),
                    'output_timezone' => $this->getOption('data_timezone'),
                    'fields' => array('year', 'month', 'day'),
                ))
            ));
        }

        if ($this->getOption('widget') === self::INPUT) {
            $this->setValueTransformer(new DateTimeToLocalizedStringTransformer(array(
                'date_format' => $this->getOption('format'),
                'time_format' => DateTimeToLocalizedStringTransformer::NONE,
                'input_timezone' => $this->getOption('data_timezone'),
                'output_timezone' => $this->getOption('user_timezone'),
            )));

            $this->setFieldMode(self::FIELD);
        } else {
            $this->setValueTransformer(new DateTimeToArrayTransformer(array(
                'input_timezone' => $this->getOption('data_timezone'),
                'output_timezone' => $this->getOption('user_timezone'),
            )));

            $this->setFieldMode(self::FORM);

            $this->add(new ChoiceField('year', array(
                'choice_list' => new PaddedChoiceList($this->getOption('years'), 2, '0', STR_PAD_LEFT),
            )));
            $this->add(new ChoiceField('month', array(
                'choice_list' => new MonthChoiceList($this->formatter, $this->getOption('months')),
            )));
            $this->add(new ChoiceField('day', array(
                'choice_list' => new PaddedChoiceList($this->getOption('days'), 2, '0', STR_PAD_LEFT),
            )));
        }
    }

    // temporary
    public function getFormatter()
    {
        return $this->formatter;
    }

    /**
     * Returns whether the year of the field's data is valid
     *
     * The year is valid if it is contained in the list passed to the field's
     * option "years".
     *
     * @return Boolean
     */
    public function isYearWithinRange()
    {
        $date = $this->getNormalizedData();

        return $this->isEmpty() || ($this->isGroup() && $this->get('year')->isEmpty())
                || in_array($date->format('Y'), $this->getOption('years'));
    }

    /**
     * Returns whether the month of the field's data is valid
     *
     * The month is valid if it is contained in the list passed to the field's
     * option "months".
     *
     * @return Boolean
     */
    public function isMonthWithinRange()
    {
        $date = $this->getNormalizedData();

        return $this->isEmpty() || ($this->isGroup() && $this->get('month')->isEmpty())
                || in_array($date->format('m'), $this->getOption('months'));
    }

    /**
     * Returns whether the day of the field's data is valid
     *
     * The day is valid if it is contained in the list passed to the field's
     * option "days".
     *
     * @return Boolean
     */
    public function isDayWithinRange()
    {
        $date = $this->getNormalizedData();

        return $this->isEmpty() || ($this->isGroup() && $this->get('day')->isEmpty())
                || in_array($date->format('d'), $this->getOption('days'));
    }

    /**
     * Returns whether the field is neither completely filled (a selected
     * value in each dropdown) nor completely empty
     *
     * @return Boolean
     */
    public function isPartiallyFilled()
    {
        if ($this->isField()) {
            return false;
        }

        if ($this->isEmpty()) {
            return false;
        }

        if ($this->get('year')->isEmpty() || $this->get('month')->isEmpty()
                || $this->get('day')->isEmpty()) {
            return true;
        }

        return false;
    }
}