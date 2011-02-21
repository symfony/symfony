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

use Symfony\Component\Form\ChoiceList\ChoiceListInterface;
use Symfony\Component\Form\ChoiceList\DefaultChoiceList;
use Symfony\Component\Form\ChoiceList\PaddedChoiceList;
use Symfony\Component\Form\ChoiceList\MonthChoiceList;
use Symfony\Component\Form\CsrfProvider\CsrfProviderInterface;
use Symfony\Component\Form\DataProcessor\RadioToArrayConverter;
use Symfony\Component\Form\FieldFactory\FieldFactoryInterface;
use Symfony\Component\Form\Renderer\DefaultRenderer;
use Symfony\Component\Form\Renderer\Theme\ThemeInterface;
use Symfony\Component\Form\Renderer\Plugin\IdPlugin;
use Symfony\Component\Form\Renderer\Plugin\NamePlugin;
use Symfony\Component\Form\Renderer\Plugin\ParameterPlugin;
use Symfony\Component\Form\Renderer\Plugin\ChoicePlugin;
use Symfony\Component\Form\Renderer\Plugin\ParentNamePlugin;
use Symfony\Component\Form\Renderer\Plugin\DatePatternPlugin;
use Symfony\Component\Form\Renderer\Plugin\MoneyPatternPlugin;
use Symfony\Component\Form\ValueTransformer\BooleanToStringTransformer;
use Symfony\Component\Form\ValueTransformer\NumberToLocalizedStringTransformer;
use Symfony\Component\Form\ValueTransformer\IntegerToLocalizedStringTransformer;
use Symfony\Component\Form\ValueTransformer\MoneyToLocalizedStringTransformer;
use Symfony\Component\Form\ValueTransformer\ScalarToChoicesTransformer;
use Symfony\Component\Form\ValueTransformer\DateTimeToArrayTransformer;
use Symfony\Component\Validator\ValidatorInterface;

class FormFactory
{
    private $theme;

    private $csrfProvider;

    private $validator;

    private $fieldFactory;

    public function __construct(ThemeInterface $theme, CsrfProviderInterface $csrfProvider, ValidatorInterface $validator, FieldFactoryInterface $fieldFactory)
    {
        $this->theme = $theme;
        $this->csrfProvider = $csrfProvider;
        $this->validator = $validator;
        $this->fieldFactory = $fieldFactory;
    }

    protected function getTheme()
    {
        return $this->theme;
    }

    protected function getCsrfProvider()
    {
        return $this->csrfProvider;
    }

    protected function getValidator()
    {
        return $this->validator;
    }

    protected function getFieldFactory()
    {
        return $this->fieldFactory;
    }

    protected function initField(FieldInterface $field, array $options = array())
    {
        $options = array_merge(array(
            'template' => 'text',
            'data' => null,
            'property_path' => (string)$field->getKey(),
            'trim' => true,
            'required' => true,
            'disabled' => false,
            'value_transformer' => null,
            'normalization_transformer' => null,
        ), $options);

        return $field
            ->setData($options['data'])
            ->setPropertyPath($options['property_path'])
            ->setTrim($options['trim'])
            ->setRequired($options['required'])
            ->setDisabled($options['disabled'])
            ->setValueTransformer($options['value_transformer'])
            ->setNormalizationTransformer($options['normalization_transformer'])
            ->setRenderer(new DefaultRenderer($this->theme, $options['template']))
            ->addRendererPlugin(new IdPlugin($field))
            ->addRendererPlugin(new NamePlugin($field))
            ->setRendererVar('field', $field)
            ->setRendererVar('class', null)
            ->setRendererVar('max_length', null)
            ->setRendererVar('size', null)
            ->setRendererVar('label', ucfirst(strtolower(str_replace('_', ' ', $key))));
    }

    protected function initForm(FormInterface $form, array $options = array())
    {
        $options = array_merge(array(
            'template' => 'form',
            'data_class' => null,
            'data_constructor' => null,
            'csrf_protection' => true,
            'csrf_field_name' => '_token',
            'csrf_provider' => $this->csrfProvider,
            'field_factory' => $this->fieldFactory,
            'validation_groups' => null,
            'virtual' => false,
            'validator' => $this->validator,
        ), $options);

        $this->initField($form, $options);

        if ($options['csrf_protection']) {
            $form->enableCsrfProtection($options['csrf_provider'], $options['csrf_field_name']);
        }

        return $form
            ->setDataClass($options['data_class'])
            ->setDataConstructor($options['data_constructor'])
            ->setFieldFactory($options['field_factory'])
            ->setValidationGroups($options['validation_groups'])
            ->setVirtual($options['virtual'])
            ->setValidator($options['validator']);
    }

    public function getField($key, array $options = array())
    {
        $field = new Field($key);

        $this->initField($field);

        return $field;
    }

    public function getForm($key, array $options = array())
    {
        $form = new Form($key);

        $this->initForm($form);

        return $form;
    }

    public function getTextField($key, array $options = array())
    {
        $options = array_merge(array(
            'template' => 'text',
            'max_length' => null,
        ), $options);

        return $this->getField($key, $options)
            ->setRendererVar('max_length', $options['max_length']);
    }

    public function getHiddenField($key, array $options = array())
    {
        $options = array_merge(array(
            'template' => 'hidden',
        ), $options);

        return $this->getField($key, 'hidden', $options)
            ->setHidden(true);
    }

    public function getNumberField($key, array $options = array())
    {
        $options = array_merge(array(
            'template' => 'number',
            // default precision is locale specific (usually around 3)
            'precision' => null,
            'grouping' => false,
            'rounding_mode' => NumberToLocalizedStringTransformer::ROUND_HALFUP,
        ), $options);

        return $this->getField($key, $options)
            ->setValueTransformer(new NumberToLocalizedStringTransformer(array(
                'precision' => $options['precision'],
                'grouping' => $options['grouping'],
                'rounding-mode' => $options['rounding_mode'],
            )));
    }

    public function getIntegerField($key, array $options = array())
    {
        $options = array_merge(array(
            'template' => 'integer',
            // default precision is locale specific (usually around 3)
            'precision' => null,
            'grouping' => false,
            // Integer cast rounds towards 0, so do the same when displaying fractions
            'rounding_mode' => IntegerToLocalizedStringTransformer::ROUND_DOWN,
        ), $options);

        return $this->getField($key, $options)
            ->setValueTransformer(new IntegerToLocalizedStringTransformer(array(
                'precision' => $options['precision'],
                'grouping' => $options['grouping'],
                'rounding-mode' => $options['rounding_mode'],
            )));
    }

    public function getMoneyField($key, array $options = array())
    {
        $options = array_merge(array(
            'template' => 'money',
            'precision' => 2,
            'grouping' => false,
            'divisor' => 1,
            'currency' => 'EUR',
        ), $options);

        return $this->getField($key, $options)
            ->setValueTransformer(new MoneyToLocalizedStringTransformer(array(
                'precision' => $options['precision'],
                'grouping' => $options['grouping'],
                'divisor' => $options['divisor'],
            )))
            ->addRendererPlugin(new MoneyPatternPlugin($options['currency']));
    }

    public function getCheckboxField($key, array $options = array())
    {
        $options = array_merge(array(
            'template' => 'checkbox',
            'value' => '1',
        ), $options);

        return $this->getField($key, $options)
            ->setValueTransformer(new BooleanToStringTransformer())
            ->setRendererVar('value', $options['value']);
    }

    public function getRadioField($key, array $options = array())
    {
        $options = array_merge(array(
            'template' => 'radio',
            'value' => null,
        ), $options);

        $field = $this->getField($key, $options);

        return $field
            ->setValueTransformer(new BooleanToStringTransformer())
            ->addRendererPlugin(new ParentNamePlugin($field))
            ->setRendererVar('value', $options['value']);
    }

    protected function getChoiceFieldForList($key, ChoiceListInterface $choiceList, array $options = array())
    {
        $options = array_merge(array(
            'template' => 'choice',
            'multiple' => false,
            'expanded' => false,
        ), $options);

        if (!$options['expanded']) {
            $field = $this->getField($key, $options);
        } else {
            $field = $this->getForm($key, $options);
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
            ->setRendererVar('multiple', $options['multiple'])
            ->setRendererVar('expanded', $options['expanded']);

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
            'template' => 'date',
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
            $field = $this->getField($key, $options)
                ->setValueTransformer(new DateTimeToLocalizedStringTransformer(array(
                    'date_format' => $options['format'],
                    'time_format' => \IntlDateFormatter::NONE,
                    'input_timezone' => $options['data_timezone'],
                    'output_timezone' => $options['user_timezone'],
                )));
        } else {
            // Only pass a subset of the options to children
            $childOptions = array_intersect_key($options, array_flip(array(
                'years',
                'months',
                'days',
            )));

            $field = $this->getForm($key, $options)
                ->add($this->getYearField('year', $childOptions))
                ->add($this->getMonthField('month', $formatter, $childOptions))
                ->add($this->getDayField('day', $childOptions))
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

        $field->setRendererVar('widget', $options['widget']);

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
            'template' => 'time',
            'widget' => 'choice',
            'type' => 'datetime',
            'with_seconds' => false,
            'pattern' => null,
            'data_timezone' => date_default_timezone_get(),
            'user_timezone' => date_default_timezone_get(),
        ), $options);

        // Only pass a subset of the options to children
        $childOptions = array_intersect_key($options, array_flip(array(
            'hours',
            'minutes',
            'seconds',
            'widget',
        )));

        $children = array('hour', 'minute');
        $field = $this->getForm($key, $options)
            ->add($this->getHourField('hour', $childOptions))
            ->add($this->getMinuteField('minute', $childOptions))
            // Don't modify \DateTime classes by reference, we treat
            // them like immutable value objects
            ->setModifyByReference(false);

        if ($options['with_seconds']) {
            $children[] = 'second';
            $field->add($this->getSecondField('second', $childOptions));
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
            ->setRendererVar('widget', $options['widget'])
            ->setRendererVar('with_seconds', $options['with_seconds']);

        return $field;
    }
}