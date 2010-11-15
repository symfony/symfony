<?php

namespace Symfony\Component\Form;

/*
 * This file is part of the Symfony framework.
 *
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

use Symfony\Component\Form\ValueTransformer\ReversedTransformer;
use Symfony\Component\Form\ValueTransformer\DateTimeToArrayTransformer;
use Symfony\Component\Form\ValueTransformer\DateTimeToStringTransformer;
use Symfony\Component\Form\ValueTransformer\DateTimeToTimestampTransformer;
use Symfony\Component\Form\ValueTransformer\ValueTransformerChain;

class TimeField extends FieldGroup
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
    protected function configure()
    {
        $this->addOption('hours', range(0, 23));
        $this->addOption('minutes', range(0, 59));
        $this->addOption('seconds', range(0, 59));
        $this->addOption('widget', self::CHOICE, self::$widgets);
        $this->addOption('type', self::DATETIME, self::$types);
        $this->addOption('data_timezone', 'UTC');
        $this->addOption('user_timezone', 'UTC');
        $this->addOption('with_seconds', false);

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
     * @return boolean
     */
    public function isHourWithinRange()
    {
        $date = $this->getNormalizedData();

        return $date === null || in_array($date->format('H'), $this->getOption('hours'));
    }

    /**
     * Returns whether the minute of the field's data is valid
     *
     * The minute is valid if it is contained in the list passed to the field's
     * option "minutes".
     *
     * @return boolean
     */
    public function isMinuteWithinRange()
    {
        $date = $this->getNormalizedData();

        return $date === null || in_array($date->format('i'), $this->getOption('minutes'));
    }

    /**
     * Returns whether the second of the field's data is valid
     *
     * The second is valid if it is contained in the list passed to the field's
     * option "seconds".
     *
     * @return boolean
     */
    public function isSecondWithinRange()
    {
        $date = $this->getNormalizedData();

        return $date === null || in_array($date->format('s'), $this->getOption('seconds'));
    }
}
