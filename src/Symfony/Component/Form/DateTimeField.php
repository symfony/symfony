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
use Symfony\Component\Form\ValueTransformer\DateTimeToArrayTransformer;
use Symfony\Component\Form\ValueTransformer\ValueTransformerChain;

/**
 * A field for editing a date and a time simultaneously
 *
 * @author Bernhard Schussek <bernhard.schussek@symfony-project.com>
 */
class DateTimeField extends FieldGroup
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
    public function configure()
    {
        $this->addOption('years', range(date('Y') - 5, date('Y') + 5));
        $this->addOption('months', range(1, 12));
        $this->addOption('days', range(1, 31));
        $this->addOption('hours', range(0, 23));
        $this->addOption('minutes', range(0, 59));
        $this->addOption('seconds', range(0, 59));
        $this->addOption('data_timezone', 'UTC');
        $this->addOption('user_timezone', 'UTC');
        $this->addOption('date_format', DateField::MEDIUM, self::$dateFormats);
        $this->addOption('date_widget', DateField::CHOICE, self::$dateWidgets);
        $this->addOption('time_widget', TimeField::CHOICE, self::$timeWidgets);
        $this->addOption('type', self::DATETIME, self::$types);
        $this->addOption('with_seconds', false);

        $this->add(new DateField('date', array(
            'type' => DateField::RAW,
            'widget' => $this->getOption('date_widget'),
            'format' => $this->getOption('date_format'),
            'data_timezone' => $this->getOption('user_timezone'),
            'user_timezone' => $this->getOption('user_timezone'),
            'years' => $this->getOption('years'),
            'months' => $this->getOption('months'),
            'days' => $this->getOption('days'),
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
