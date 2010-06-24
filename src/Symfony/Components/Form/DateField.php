<?php

namespace Symfony\Components\Form;

use Symfony\Components\Form\ValueTransformer\ReversedTransformer;
use Symfony\Components\Form\ValueTransformer\StringToDateTimeTransformer;
use Symfony\Components\Form\ValueTransformer\TimestampToDateTimeTransformer;
use Symfony\Components\Form\ValueTransformer\ValueTransformerChain;
use Symfony\Components\Form\ValueTransformer\DateTimeToLocalizedStringTransformer;
use Symfony\Components\Form\ValueTransformer\DateTimeToArrayTransformer;

/*
 * This file is part of the symfony package.
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
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
     * Configures the text field.
     *
     * Available options:
     *
     *  * widget:         How to render the field ("input" or "select"). Default: "input"
     *  * years:          An array of years for the year select tag (optional)
     *  * months:         An array of months for the month select tag (optional)
     *  * days:           An array of days for the day select tag (optional)
     *  * format:         See DateValueTransformer. Default: medium
     *  * type:           The type of the date ("date", "datetime" or "timestamp"). Default: "date"
     *  * data_timezone:  The timezone of the data
     *  * user_timezone:  The timezone of the user entering a new value
     *  * pattern:        The pattern for the select boxes when "widget" is "select".
     *                    You can use the placeholders "%year%", "%month%" and "%day%".
     *                    Default: locale dependent
     *
     * @param array $options Options for this field
     * @throws \InvalidArgumentException  Thrown if you want to show a timestamp with the select widget.
     */
    protected function configure()
    {
        $this->addOption('years', range(date('Y') - 5, date('Y') + 5));
        $this->addOption('months', range(1, 12));
        $this->addOption('days', range(1, 31));
        $this->addOption('format', self::MEDIUM, self::$formats);
        $this->addOption('type', self::DATETIME, self::$types);
        $this->addOption('data_timezone', 'UTC');
        $this->addOption('user_timezone', 'UTC');
        $this->addOption('widget', self::CHOICE, self::$widgets);
        $this->addOption('pattern');

        $this->formatter = new \IntlDateFormatter(
            $this->locale,
            self::$intlFormats[$this->getOption('format')],
            \IntlDateFormatter::NONE
        );

        $transformers = array();

        if ($this->getOption('type') === self::STRING) {
            $transformers[] = new StringToDateTimeTransformer(array(
                'input_timezone' => $this->getOption('data_timezone'),
                'output_timezone' => $this->getOption('data_timezone'),
                'format' => 'Y-m-d',
            ));
        } else if ($this->getOption('type') === self::TIMESTAMP) {
            $transformers[] = new TimestampToDateTimeTransformer(array(
                'output_timezone' => $this->getOption('data_timezone'),
                'input_timezone' => $this->getOption('data_timezone'),
            ));
        } else if ($this->getOption('type') === self::RAW) {
            $transformers[] = new ReversedTransformer(new DateTimeToArrayTransformer(array(
                'input_timezone' => $this->getOption('data_timezone'),
                'output_timezone' => $this->getOption('data_timezone'),
                'fields' => array('year', 'month', 'day'),
            )));
        }

        if ($this->getOption('widget') === self::INPUT) {
            $transformers[] = new DateTimeToLocalizedStringTransformer(array(
                'date_format' => $this->getOption('format'),
                'time_format' => DateTimeToLocalizedStringTransformer::NONE,
                'input_timezone' => $this->getOption('data_timezone'),
                'output_timezone' => $this->getOption('user_timezone'),
            ));

            $this->setFieldMode(self::FIELD);
        } else {
            $transformers[] = new DateTimeToArrayTransformer(array(
                'input_timezone' => $this->getOption('data_timezone'),
                'output_timezone' => $this->getOption('user_timezone'),
            ));

            $this->setFieldMode(self::GROUP);

            $this->add(new ChoiceField('year', array(
                'choices' => $this->generatePaddedChoices($this->getOption('years'), 4),
            )));
            $this->add(new ChoiceField('month', array(
                'choices' => $this->generateMonthChoices($this->getOption('months')),
            )));
            $this->add(new ChoiceField('day', array(
                'choices' => $this->generatePaddedChoices($this->getOption('days'), 2),
            )));
        }

        if (count($transformers) > 0) {
            $this->setValueTransformer(new ValueTransformerChain($transformers));
        }
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
     * Generates an array of localized month choices
     *
     * @param  array $months  The month numbers to generate
     * @return array          The localized months respecting the configured
     *                        locale and date format
     */
    protected function generateMonthChoices(array $months)
    {
        $pattern = $this->formatter->getPattern();

        if (preg_match('/M+/', $pattern, $matches)) {
            $this->formatter->setPattern($matches[0]);
            $choices = array();

            foreach ($months as $month) {
                $choices[$month] = $this->formatter->format(gmmktime(0, 0, 0, $month));
            }

            $this->formatter->setPattern($pattern);
        } else {
            $choices = $this->generatePaddedChoices($months, 2);
        }

        return $choices;
    }

    /**
     * {@inheritDoc}
     */
    public function render(array $attributes = array())
    {
        if ($this->getOption('widget') === self::INPUT) {
            return $this->generator->tag('input', array_merge(array(
                'id'    => $this->getId(),
                'name'  => $this->getName(),
                'value' => $this->getDisplayedData(),
                'type'  => 'text',
            ), $attributes));
        } else {
            // set order as specified in the pattern
            if ($this->getOption('pattern')) {
                $pattern = $this->getOption('pattern');
            }
            // set right order with respect to locale (e.g.: de_DE=dd.MM.yy; en_US=M/d/yy)
            // lookup various formats at http://userguide.icu-project.org/formatparse/datetime
            else if (preg_match('/^([yMd]+).+([yMd]+).+([yMd]+)$/', $this->formatter->getPattern())) {
                $pattern = preg_replace(array('/y+/', '/M+/', '/d+/'), array('%year%', '%month%', '%day%'), $this->formatter->getPattern());
            }
            // default fallback
            else {
                $pattern = '%year%-%month%-%day%';
            }

            return str_replace(array('%year%', '%month%', '%day%'), array(
                $this->get('year')->render($attributes),
                $this->get('month')->render($attributes),
                $this->get('day')->render($attributes),
            ), $pattern);
        }
    }
}