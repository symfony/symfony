<?php

namespace Symfony\Components\Form;

use Symfony\Components\Form\ValueTransformer\StringToDateTimeTransformer;
use Symfony\Components\Form\ValueTransformer\TimestampToDateTimeTransformer;
use Symfony\Components\Form\ValueTransformer\DateTimeToArrayTransformer;
use Symfony\Components\Form\ValueTransformer\ValueTransformerChain;

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
        $this->addOption('date_widget', DateField::INPUT, self::$dateWidgets);
        $this->addOption('time_widget', TimeField::CHOICE, self::$timeWidgets);
        $this->addOption('type', self::DATETIME, self::$types);
        $this->addOption('with_seconds', false);

        $this->add(new DateField('date', array(
            'type' => DateField::RAW,
            'widget' => $this->getOption('date_widget'),
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

        $transformers = array();

        if ($this->getOption('type') == self::STRING) {
            $transformers[] = new StringToDateTimeTransformer(array(
                'input_timezone' => $this->getOption('data_timezone'),
                'output_timezone' => $this->getOption('data_timezone'),
            ));
        } else if ($this->getOption('type') == self::TIMESTAMP) {
            $transformers[] = new TimestampToDateTimeTransformer(array(
                'input_timezone' => $this->getOption('data_timezone'),
                'output_timezone' => $this->getOption('data_timezone'),
            ));
        }

        $transformers[] = new DateTimeToArrayTransformer(array(
            'input_timezone' => $this->getOption('data_timezone'),
            'output_timezone' => $this->getOption('user_timezone'),
        ));

        $this->setValueTransformer(new ValueTransformerChain($transformers));
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

    /**
     * {@inheritDoc}
     */
    public function render(array $attributes = array())
    {
        $html = $this->get('date')->render($attributes)."\n";
        $html .= $this->get('time')->render($attributes);

        return $html;
    }
}
