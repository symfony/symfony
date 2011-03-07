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
use Symfony\Component\Form\ValueTransformer\DateTimeToArrayTransformer;
use Symfony\Component\Form\ValueTransformer\DateTimeToStringTransformer;
use Symfony\Component\Form\ValueTransformer\DateTimeToTimestampTransformer;
use Symfony\Component\Form\ValueTransformer\ValueTransformerChain;

/**
 * Represents a time field.
 *
 * Available options:
 *
 *  * widget:         How to render the time field ("input" or "choice"). Default: "choice".
 *  * type:           The type of the date stored on the object. Default: "datetime":
 *                    * datetime:   A DateTime object;
 *                    * string:     A raw string (e.g. 2011-05-01 12:30:00, Y-m-d H:i:s);
 *                    * timestamp:  A unix timestamp (e.g. 1304208000).
 *                    * raw:        An hour, minute, second array
 *  * with_seconds    Whether or not to create a field for seconds. Default: false.
 *
 *  * hours:          An array of hours for the hour select tag.
 *  * minutes:        An array of minutes for the minute select tag.
 *  * seconds:        An array of seconds for the second select tag.
 *
 *  * data_timezone:  The timezone of the data. Default: UTC.
 *  * user_timezone:  The timezone of the user entering a new value. Default: UTC.
 */
class TimeField extends Form
{
    const INPUT = 'input';
    const CHOICE = 'choice';

    const DATETIME = 'datetime';
    const STRING = 'string';
    const TIMESTAMP = 'timestamp';
    const RAW = 'raw';

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

    /**
     * {@inheritDoc}
     */
    protected function configure()
    {
        $this->addOption('widget', self::CHOICE, self::$widgets);
        $this->addOption('type', self::DATETIME, self::$types);
        $this->addOption('with_seconds', false);

        $this->addOption('hours', range(0, 23));
        $this->addOption('minutes', range(0, 59));
        $this->addOption('seconds', range(0, 59));

        $this->addOption('data_timezone', date_default_timezone_get());
        $this->addOption('user_timezone', date_default_timezone_get());

        if ($this->getOption('widget') == self::INPUT) {
            $this->add(new TextField('hour', array('max_length' => 2)));
            $this->add(new TextField('minute', array('max_length' => 2)));

            if ($this->getOption('with_seconds')) {
                $this->add(new TextField('second', array('max_length' => 2)));
            }
        } else {
            $this->add(new ChoiceField('hour', array(
                'choices' => $this->generatePaddedChoices($this->getOption('hours'), 2),
            )));
            $this->add(new ChoiceField('minute', array(
                'choices' => $this->generatePaddedChoices($this->getOption('minutes'), 2),
            )));

            if ($this->getOption('with_seconds')) {
                $this->add(new ChoiceField('second', array(
                    'choices' => $this->generatePaddedChoices($this->getOption('seconds'), 2),
                )));
            }
        }

        $fields = array('hour', 'minute');

        if ($this->getOption('with_seconds')) {
            $fields[] = 'second';
        }

        if ($this->getOption('type') == self::STRING) {
            $this->setNormalizationTransformer(new ReversedTransformer(
                new DateTimeToStringTransformer(array(
                    'format' => 'H:i:s',
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
        } else if ($this->getOption('type') === self::RAW) {
            $this->setNormalizationTransformer(new ReversedTransformer(
                new DateTimeToArrayTransformer(array(
                    'input_timezone' => $this->getOption('data_timezone'),
                    'output_timezone' => $this->getOption('data_timezone'),
                    'fields' => $fields,
                ))
            ));
        }

        $this->setValueTransformer(new DateTimeToArrayTransformer(array(
            'input_timezone' => $this->getOption('data_timezone'),
            'output_timezone' => $this->getOption('user_timezone'),
            // if the field is rendered as choice field, the values should be trimmed
            // of trailing zeros to render the selected choices correctly
            'pad' => $this->getOption('widget') == self::INPUT,
            'fields' => $fields,
        )));
    }

    public function isField()
    {
        return self::INPUT === $this->getOption('widget');
    }

    public function isWithSeconds()
    {
        return $this->getOption('with_seconds');
    }

    /**
     * Generates an array of choices for the given values
     *
     * If the values are shorter than $padLength characters, they are padded with
     * zeros on the left side.
     *
     * @param  array   $values     The available choices
     * @param  integer $padLength  The length to pad the choices
     * @return array               An array with the input values as keys and the
     *                             padded values as values
     */
    protected function generatePaddedChoices(array $values, $padLength)
    {
        $choices = array();

        foreach ($values as $value) {
            $choices[$value] = str_pad($value, $padLength, '0', STR_PAD_LEFT);
        }

        return $choices;
    }

    /**
     * Returns whether the hour of the field's data is valid
     *
     * The hour is valid if it is contained in the list passed to the field's
     * option "hours".
     *
     * @return Boolean
     */
    public function isHourWithinRange()
    {
        $date = $this->getNormalizedData();

        return $this->isEmpty() || $this->get('hour')->isEmpty()
                || in_array($date->format('H'), $this->getOption('hours'));
    }

    /**
     * Returns whether the minute of the field's data is valid
     *
     * The minute is valid if it is contained in the list passed to the field's
     * option "minutes".
     *
     * @return Boolean
     */
    public function isMinuteWithinRange()
    {
        $date = $this->getNormalizedData();

        return $this->isEmpty() || $this->get('minute')->isEmpty()
                || in_array($date->format('i'), $this->getOption('minutes'));
    }

    /**
     * Returns whether the second of the field's data is valid
     *
     * The second is valid if it is contained in the list passed to the field's
     * option "seconds".
     *
     * @return Boolean
     */
    public function isSecondWithinRange()
    {
        $date = $this->getNormalizedData();

        return $this->isEmpty() || !$this->has('second') || $this->get('second')->isEmpty()
                || in_array($date->format('s'), $this->getOption('seconds'));
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

        if ($this->get('hour')->isEmpty() || $this->get('minute')->isEmpty()
                || ($this->isWithSeconds() && $this->get('second')->isEmpty())) {
            return true;
        }

        return false;
    }
}
