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

use Symfony\Component\Form\ValueTransformer\BooleanToStringTransformer;
use Symfony\Component\Form\ChoiceList\ChoiceListInterface;
use Symfony\Component\Form\ChoiceList\DefaultChoiceList;
use Symfony\Component\Form\ChoiceList\PaddedChoiceList;
use Symfony\Component\Form\ChoiceList\MonthChoiceList;
use Symfony\Component\Form\DataProcessor\RadioToArrayConverter;
use Symfony\Component\Form\Renderer\DefaultRenderer;
use Symfony\Component\Form\Renderer\Theme\ThemeInterface;
use Symfony\Component\Form\Renderer\Plugin\IdPlugin;
use Symfony\Component\Form\Renderer\Plugin\NamePlugin;
use Symfony\Component\Form\Renderer\Plugin\ParameterPlugin;
use Symfony\Component\Form\Renderer\Plugin\ChoicePlugin;
use Symfony\Component\Form\Renderer\Plugin\ParentNamePlugin;
use Symfony\Component\Form\Renderer\Plugin\DatePatternPlugin;
use Symfony\Component\Form\ValueTransformer\ScalarToChoicesTransformer;
use Symfony\Component\Form\ValueTransformer\DateTimeToArrayTransformer;

class FormFactory
{
    private $theme;

    public function __construct(ThemeInterface $theme)
    {
        $this->setTheme($theme);
    }

    public function setTheme(ThemeInterface $theme)
    {
        $this->theme = $theme;
    }

    public function getTheme()
    {
        return $this->theme;
    }

    protected function getField($key, $template)
    {
        $field = new Field($key);

        return $field
            ->setRenderer(new DefaultRenderer($this->theme, $template))
            ->addRendererPlugin(new IdPlugin($field))
            ->addRendererPlugin(new NamePlugin($field));
    }

    protected function getForm($key, $template)
    {
        $field = new Form($key);

        return $field
            ->setRenderer(new DefaultRenderer($this->theme, $template))
            ->addRendererPlugin(new IdPlugin($field))
            ->addRendererPlugin(new NamePlugin($field));
    }

    public function getTextField($key, array $options = array())
    {
        $options = array_merge(array(
            'max_length' => null,
        ), $options);

        return $this->getField($key, 'text')
            ->addRendererPlugin(new ParameterPlugin('max_length', $options['max_length']));
    }

    public function getCheckboxField($key, array $options = array())
    {
        $options = array_merge(array(
            'value' => '1',
        ), $options);

        return $this->getField($key, 'checkbox')
            ->setValueTransformer(new BooleanToStringTransformer())
            ->addRendererPlugin(new ParameterPlugin('value', $options['value']));
    }

    public function getRadioField($key, array $options = array())
    {
        $options = array_merge(array(
            'value' => null,
        ), $options);

        $field = $this->getField($key, 'radio');

        return $field
            ->setValueTransformer(new BooleanToStringTransformer())
            ->addRendererPlugin(new ParentNamePlugin($field))
            ->addRendererPlugin(new ParameterPlugin('value', $options['value']));
    }

    protected function getChoiceFieldForList($key, ChoiceListInterface $choiceList, array $options = array())
    {
        $options = array_merge(array(
            'multiple' => false,
            'expanded' => false,
        ), $options);

        if (!$options['expanded']) {
            $field = $this->getField($key, 'choice');
        } else {
            $field = $this->getForm($key, 'choice');
            $choices = array_merge($choiceList->getPreferredChoices(), $choiceList->getOtherChoices());

            foreach ($choices as $choice => $value) {
                if ($options['multiple']) {
                    $field->add($this->getCheckboxField($choice, array(
                        'value' => $choice,
                    )));
                } else {
                    $field->add($this->getRadioField($choice, array(
                        'value' => $choice,
                    )));
                }
            }
        }

        $field->addRendererPlugin(new ChoicePlugin($choiceList))
            ->addRendererPlugin(new ParameterPlugin('multiple', $options['multiple']))
            ->addRendererPlugin(new ParameterPlugin('expanded', $options['expanded']));

        if ($options['multiple'] && $options['expanded']) {
            $field->setValueTransformer(new ArrayToChoicesTransformer($choiceList));
        }

        if (!$options['multiple'] && $options['expanded']) {
            $field->setValueTransformer(new ScalarToChoicesTransformer($choiceList));
            $field->setDataPreprocessor(new RadioToArrayConverter());
        }

        if ($options['multiple'] && !$options['expanded']) {
            $field->addRendererPlugin(new SelectMultipleNamePlugin($field));
        }

        return $field;
    }

    public function getChoiceField($key, array $options = array())
    {
        $options = array_merge(array(
            'choices' => array(),
            'preferred_choices' => array(),
        ), $options);

        $choiceList = new DefaultChoiceList(
            $options['choices'],
            $options['preferred_choices']
        );

        return $this->getChoiceFieldForList($key, $choiceList, $options);
    }

    public function getCountryField($key, array $options = array())
    {
        $options = array_merge(array(
            'choices' => Locale::getDisplayCountries(\Locale::getDefault()),
        ), $options);

        return $this->getChoiceField($key, $options);
    }

    public function getLanguageField($key, array $options = array())
    {
        $options = array_merge(array(
            'choices' => Locale::getDisplayLanguages(\Locale::getDefault()),
        ), $options);

        return $this->getChoiceField($key, $options);
    }

    public function getLocaleField($key, array $options = array())
    {
        $options = array_merge(array(
            'choices' => Locale::getDisplayLocales(\Locale::getDefault()),
        ), $options);

        return $this->getChoiceField($key, $options);
    }

    protected function getDayField($key, array $options = array())
    {
        $options = array_merge(array(
            'days' => range(1, 31),
            'preferred_choices' => array(),
        ), $options);

        $choiceList = new PaddedChoiceList(
            $options['days'], 2, '0', STR_PAD_LEFT, $options['preferred_choices']
        );

        return $this->getChoiceFieldForList($key, $choiceList, $options);
    }

    protected function getMonthField($key, \IntlDateFormatter $formatter, array $options = array())
    {
        $options = array_merge(array(
            'months' => range(1, 12),
            'preferred_choices' => array(),
        ), $options);

        $choiceList = new MonthChoiceList(
            $formatter, $options['months'], $options['preferred_choices']
        );

        return $this->getChoiceFieldForList($key, $choiceList, $options);
    }

    protected function getYearField($key, array $options = array())
    {
        $options = array_merge(array(
            'years' => range(date('Y') - 5, date('Y') + 5),
            'preferred_choices' => array(),
        ), $options);

        $choiceList = new PaddedChoiceList(
            $options['years'], 4, '0', STR_PAD_LEFT, $options['preferred_choices']
        );

        return $this->getChoiceFieldForList($key, $choiceList, $options);
    }

    protected function getHourField($key, array $options = array())
    {
        $options = array_merge(array(
            'widget' => 'choice',
            'hours' => range(0, 23),
            'preferred_choices' => array(),
        ), $options);

        if ($options['widget'] == 'text') {
            return $this->getTextField($key, array('max_length' => 2));
        } else {
            $choiceList = new PaddedChoiceList(
                $options['hours'], 2, '0', STR_PAD_LEFT, $options['preferred_choices']
            );

            return $this->getChoiceFieldForList($key, $choiceList, $options);
        }
    }

    protected function getMinuteField($key, array $options = array())
    {
        $options = array_merge(array(
            'widget' => 'choice',
            'minutes' => range(0, 59),
            'preferred_choices' => array(),
        ), $options);

        if ($options['widget'] == 'text') {
            return $this->getTextField($key, array('max_length' => 2));
        } else {
            $choiceList = new PaddedChoiceList(
                $options['minutes'], 2, '0', STR_PAD_LEFT, $options['preferred_choices']
            );

            return $this->getChoiceFieldForList($key, $choiceList, $options);
        }
    }

    protected function getSecondField($key, array $options = array())
    {
        $options = array_merge(array(
            'widget' => 'choice',
            'seconds' => range(0, 59),
            'preferred_choices' => array(),
        ), $options);

        if ($options['widget'] == 'text') {
            return $this->getTextField($key, array('max_length' => 2));
        } else {
            $choiceList = new PaddedChoiceList(
                $options['seconds'], 2, '0', STR_PAD_LEFT, $options['preferred_choices']
            );

            return $this->getChoiceFieldForList($key, $choiceList, $options);
        }
    }

    public function getDateField($key, array $options = array())
    {
        $options = array_merge(array(
            'widget' => 'choice',
            'type' => 'datetime',
            'pattern' => null,
            'format' => \IntlDateFormatter::MEDIUM,
            'data_timezone' => date_default_timezone_get(),
            'user_timezone' => date_default_timezone_get(),
        ), $options);

        $formatter = new \IntlDateFormatter(
            \Locale::getDefault(),
            $options['format'],
            \IntlDateFormatter::NONE
        );

        if ($options['widget'] === 'text') {
            $field = $this->getField($key, 'date')
                ->setValueTransformer(new DateTimeToLocalizedStringTransformer(array(
                    'date_format' => $options['format'],
                    'time_format' => DateTimeToLocalizedStringTransformer::NONE,
                    'input_timezone' => $options['data_timezone'],
                    'output_timezone' => $options['user_timezone'],
                )));
        } else {
            $field = $this->getForm($key, 'date')
                ->add($this->getYearField('year', $options))
                ->add($this->getMonthField('month', $formatter, $options))
                ->add($this->getDayField('day', $options))
                ->setValueTransformer(new DateTimeToArrayTransformer(array(
                    'input_timezone' => $options['data_timezone'],
                    'output_timezone' => $options['user_timezone'],
                )))
                ->addRendererPlugin(new DatePatternPlugin($formatter))
                // Don't modify \DateTime classes by reference, we treat
                // them like immutable value objects
                ->setModifyByReference(false);
        }

        if ($options['type'] === 'string') {
            $field->setNormalizationTransformer(new ReversedTransformer(
                new DateTimeToStringTransformer(array(
                    'input_timezone' => $options['data_timezone'],
                    'output_timezone' => $options['data_timezone'],
                    'format' => 'Y-m-d',
                ))
            ));
        } else if ($options['type'] === 'timestamp') {
            $field->setNormalizationTransformer(new ReversedTransformer(
                new DateTimeToTimestampTransformer(array(
                    'output_timezone' => $options['data_timezone'],
                    'input_timezone' => $options['data_timezone'],
                ))
            ));
        } else if ($options['type'] === 'raw') {
            $field->setNormalizationTransformer(new ReversedTransformer(
                new DateTimeToArrayTransformer(array(
                    'input_timezone' => $options['data_timezone'],
                    'output_timezone' => $options['data_timezone'],
                    'fields' => array('year', 'month', 'day'),
                ))
            ));
        }

        $field->addRendererPlugin(new ParameterPlugin('widget', $options['widget']));

        return $field;
    }

    public function getBirthdayField($key, array $options = array())
    {
        $options = array_merge(array(
            'years' => range($currentYear-120, $currentYear),
        ), $options);

        return $this->getDateField($key, $options);
    }

    public function getTimeField($key, array $options = array())
    {
        $options = array_merge(array(
            'widget' => 'choice',
            'type' => 'datetime',
            'with_seconds' => false,
            'pattern' => null,
            'data_timezone' => date_default_timezone_get(),
            'user_timezone' => date_default_timezone_get(),
        ), $options);

        $children = array('hour', 'minute');
        $field = $this->getForm($key, 'time')
            ->add($this->getHourField('hour', $options))
            ->add($this->getMinuteField('minute', $options))
            // Don't modify \DateTime classes by reference, we treat
            // them like immutable value objects
            ->setModifyByReference(false);

        if ($options['with_seconds']) {
            $children[] = 'second';
            $field->add($this->getSecondField('second', $options));
        }

        if ($options['type'] == 'string') {
            $field->setNormalizationTransformer(new ReversedTransformer(
                new DateTimeToStringTransformer(array(
                    'format' => 'H:i:s',
                    'input_timezone' => $options['data_timezone'],
                    'output_timezone' => $options['data_timezone'],
                ))
            ));
        } else if ($options['type'] == 'timestamp') {
            $field->setNormalizationTransformer(new ReversedTransformer(
                new DateTimeToTimestampTransformer(array(
                    'input_timezone' => $options['data_timezone'],
                    'output_timezone' => $options['data_timezone'],
                ))
            ));
        } else if ($options['type'] === 'raw') {
            $field->setNormalizationTransformer(new ReversedTransformer(
                new DateTimeToArrayTransformer(array(
                    'input_timezone' => $options['data_timezone'],
                    'output_timezone' => $options['data_timezone'],
                    'fields' => $children,
                ))
            ));
        }

        $field
            ->setValueTransformer(new DateTimeToArrayTransformer(array(
                'input_timezone' => $options['data_timezone'],
                'output_timezone' => $options['user_timezone'],
                // if the field is rendered as choice field, the values should be trimmed
                // of trailing zeros to render the selected choices correctly
                'pad' => $options['widget'] === 'text',
                'fields' => $children,
            )))
            ->addRendererPlugin(new ParameterPlugin('widget', $options['widget']))
            ->addRendererPlugin(new ParameterPlugin('with_seconds', $options['with_seconds']));

        return $field;
    }
}