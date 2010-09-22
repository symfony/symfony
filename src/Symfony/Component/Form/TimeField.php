<?php

namespace Symfony\Component\Form;

use Symfony\Component\Form\ValueTransformer\ReversedTransformer;
use Symfony\Component\Form\ValueTransformer\DateTimeToArrayTransformer;
use Symfony\Component\Form\ValueTransformer\StringToDateTimeTransformer;
use Symfony\Component\Form\ValueTransformer\TimestampToDateTimeTransformer;
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

        $transformers = array();

        if ($this->getOption('type') == self::STRING) {
            $transformers[] = new StringToDateTimeTransformer(array(
                'format' => 'H:i:s',
                'input_timezone' => $this->getOption('data_timezone'),
                'output_timezone' => $this->getOption('data_timezone'),
            ));
        } else if ($this->getOption('type') == self::TIMESTAMP) {
            $transformers[] = new TimestampToDateTimeTransformer(array(
                'input_timezone' => $this->getOption('data_timezone'),
                'output_timezone' => $this->getOption('data_timezone'),
            ));
        } else if ($this->getOption('type') === self::RAW) {
            $transformers[] = new ReversedTransformer(new DateTimeToArrayTransformer(array(
                'input_timezone' => $this->getOption('data_timezone'),
                'output_timezone' => $this->getOption('data_timezone'),
                'fields' => array('hour', 'minute', 'second'),
            )));
        }

        $transformers[] = new DateTimeToArrayTransformer(array(
            'input_timezone' => $this->getOption('data_timezone'),
            'output_timezone' => $this->getOption('user_timezone'),
            // if the field is rendered as choice field, the values should be trimmed
            // of trailing zeros to render the selected choices correctly
            'pad' => $this->getOption('widget') == self::INPUT,
        ));

        $this->setValueTransformer(new ValueTransformerChain($transformers));
    }

    /**
     * {@inheritDoc}
     */
    public function render(array $attributes = array())
    {
        if ($this->getOption('widget') == self::INPUT) {
            $attributes = array_merge(array(
                'size' => '1',
            ), $attributes);
        }

        $html = $this->get('hour')->render($attributes);
        $html .= ':' . $this->get('minute')->render($attributes);

        if ($this->getOption('with_seconds')) {
            $html .= ':' . $this->get('second')->render($attributes);
        }

        return $html;
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
}
