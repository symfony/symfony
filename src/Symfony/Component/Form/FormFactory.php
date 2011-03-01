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
use Symfony\Component\Form\ChoiceList\TimeZoneChoiceList;
use Symfony\Component\Form\ChoiceList\EntityChoiceList;
use Symfony\Component\Form\CsrfProvider\CsrfProviderInterface;
use Symfony\Component\Form\EventListener\ResizeFormListener;
use Symfony\Component\Form\Filter\RadioInputFilter;
use Symfony\Component\Form\Filter\FixUrlProtocolFilter;
use Symfony\Component\Form\Filter\FileUploadFilter;
use Symfony\Component\Form\FieldFactory\FieldFactoryInterface;
use Symfony\Component\Form\Renderer\DefaultRenderer;
use Symfony\Component\Form\Renderer\Theme\ThemeInterface;
use Symfony\Component\Form\Renderer\Plugin\FieldPlugin;
use Symfony\Component\Form\Renderer\Plugin\FormPlugin;
use Symfony\Component\Form\Renderer\Plugin\ParameterPlugin;
use Symfony\Component\Form\Renderer\Plugin\ChoicePlugin;
use Symfony\Component\Form\Renderer\Plugin\ParentNamePlugin;
use Symfony\Component\Form\Renderer\Plugin\DatePatternPlugin;
use Symfony\Component\Form\Renderer\Plugin\MoneyPatternPlugin;
use Symfony\Component\Form\Renderer\Plugin\PasswordValuePlugin;
use Symfony\Component\Form\Renderer\Plugin\SelectMultipleNamePlugin;
use Symfony\Component\Form\Renderer\Plugin\CheckedPlugin;
use Symfony\Component\Form\ValueTransformer\BooleanToStringTransformer;
use Symfony\Component\Form\ValueTransformer\NumberToLocalizedStringTransformer;
use Symfony\Component\Form\ValueTransformer\IntegerToLocalizedStringTransformer;
use Symfony\Component\Form\ValueTransformer\MoneyToLocalizedStringTransformer;
use Symfony\Component\Form\ValueTransformer\PercentToLocalizedStringTransformer;
use Symfony\Component\Form\ValueTransformer\ScalarToChoicesTransformer;
use Symfony\Component\Form\ValueTransformer\DateTimeToArrayTransformer;
use Symfony\Component\Form\ValueTransformer\DateTimeToStringTransformer;
use Symfony\Component\Form\ValueTransformer\DateTimeToLocalizedStringTransformer;
use Symfony\Component\Form\ValueTransformer\DateTimeToTimestampTransformer;
use Symfony\Component\Form\ValueTransformer\ReversedTransformer;
use Symfony\Component\Form\ValueTransformer\EntityToIdTransformer;
use Symfony\Component\Form\ValueTransformer\EntitiesToArrayTransformer;
use Symfony\Component\Form\ValueTransformer\ValueTransformerChain;
use Symfony\Component\Form\ValueTransformer\ArrayToChoicesTransformer;
use Symfony\Component\Form\ValueTransformer\ArrayToPartsTransformer;
use Symfony\Component\Form\ValueTransformer\ValueToDuplicatesTransformer;
use Symfony\Component\Form\ValueTransformer\FileToArrayTransformer;
use Symfony\Component\Form\ValueTransformer\FileToStringTransformer;
use Symfony\Component\Form\ValueTransformer\MergeCollectionTransformer;
use Symfony\Component\Validator\ValidatorInterface;
use Symfony\Component\Locale\Locale;
use Symfony\Component\HttpFoundation\File\TemporaryStorage;
use Doctrine\ORM\EntityManager;

class FormFactory
{
    private $theme;

    private $csrfProvider;

    private $validator;

    private $fieldFactory;

    private $storage;

    private $entityManager;

    public function __construct(ThemeInterface $theme,
            CsrfProviderInterface $csrfProvider,
            ValidatorInterface $validator,
            FieldFactoryInterface $fieldFactory,
            TemporaryStorage $storage,
            EntityManager $entityManager)
    {
        $this->theme = $theme;
        $this->csrfProvider = $csrfProvider;
        $this->validator = $validator;
        $this->fieldFactory = $fieldFactory;
        $this->storage = $storage;
        $this->entityManager = $entityManager;
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
            ->addRendererPlugin(new FieldPlugin($field))
            ->setRendererVar('class', null)
            ->setRendererVar('max_length', null)
            ->setRendererVar('size', null)
            ->setRendererVar('label', ucfirst(strtolower(str_replace('_', ' ', $field->getKey()))));
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
            ->setValidator($options['validator'])
            ->addRendererPlugin(new FormPlugin($form));
    }

    public function getField($key, array $options = array())
    {
        $field = new Field($key);

        $this->initField($field, $options);

        return $field;
    }

    public function getForm($key, array $options = array())
    {
        $form = new Form($key);

        $this->initForm($form, $options);

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

    public function getTextareaField($key, array $options = array())
    {
        $options = array_merge(array(
            'template' => 'textarea',
        ), $options);

        return $this->getField($key, $options);
    }

    public function getPasswordField($key, array $options = array())
    {
        $options = array_merge(array(
            'template' => 'password',
            'always_empty' => true,
        ), $options);

        $field = $this->getTextField($key, $options);

        return $field
            ->addRendererPlugin(new PasswordValuePlugin($field, $options['always_empty']));
    }

    public function getHiddenField($key, array $options = array())
    {
        $options = array_merge(array(
            'template' => 'hidden',
        ), $options);

        return $this->getField($key, $options);
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

    public function getPercentField($key, array $options = array())
    {
        $options = array_merge(array(
            'template' => 'percent',
            'precision' => 0,
            'type' => 'fractional',
        ), $options);

        return $this->getField($key, $options)
            ->setValueTransformer(new PercentToLocalizedStringTransformer(array(
                'precision' => $options['precision'],
                'type' => $options['type'],
            )));
    }

    public function getCheckboxField($key, array $options = array())
    {
        $options = array_merge(array(
            'template' => 'checkbox',
            'value' => '1',
        ), $options);

        $field = $this->getField($key, $options);

        return $field
            ->setValueTransformer(new BooleanToStringTransformer())
            ->addRendererPlugin(new CheckedPlugin($field))
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
            ->addRendererPlugin(new CheckedPlugin($field))
            ->addRendererPlugin(new ParentNamePlugin($field))
            ->setRendererVar('value', $options['value']);
    }

    public function getUrlField($key, array $options = array())
    {
        $options = array_merge(array(
            'default_protocol' => 'http',
        ), $options);

        return $this->getTextField($key, $options)
            ->prependFilter(new FixUrlProtocolFilter($options['default_protocol']));
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
            $choices = array_replace($choiceList->getPreferredChoices(), $choiceList->getOtherChoices());

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
            $field->prependFilter(new RadioInputFilter());
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

    public function getEntityChoiceField($key, array $options = array())
    {
        $options = array_merge(array(
            'em' => $this->entityManager,
            'class' => null,
            'property' => null,
            'query_builder' => null,
            'choices' => array(),
            'preferred_choices' => array(),
            'multiple' => false,
            'expanded' => false,
        ), $options);

        $choiceList = new EntityChoiceList(
            $options['em'],
            $options['class'],
            $options['property'],
            $options['query_builder'],
            $options['choices'],
            $options['preferred_choices']
        );

        $field = $this->getChoiceFieldForList($key, $choiceList, $options);

        $transformers = array();

        if ($options['multiple']) {
            $transformers[] = new MergeCollectionTransformer($field);
            $transformers[] = new EntitiesToArrayTransformer($choiceList);

            if ($options['expanded']) {
                $transformers[] = new ArrayToChoicesTransformer($choiceList);
            }
        } else {
            $transformers[] = new EntityToIdTransformer($choiceList);

            if ($options['expanded']) {
                $transformers[] = new ScalarToChoicesTransformer($choiceList);
            }
        }

        if (count($transformers) > 1) {
            $field->setValueTransformer(new ValueTransformerChain($transformers));
        } else {
            $field->setValueTransformer(current($transformers));
        }

        return $field;
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

    public function getTimeZoneField($key, array $options = array())
    {
        $options = array_merge(array(
            'preferred_choices' => array(),
        ), $options);

        $choiceList = new TimeZoneChoiceList($options['preferred_choices']);

        return $this->getChoiceFieldForList($key, $choiceList, $options);
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
                    'fields' => array('year', 'month', 'day'),
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
        } else if ($options['type'] === 'array') {
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

        $parts = array('hour', 'minute');
        $field = $this->getForm($key, $options)
            ->add($this->getHourField('hour', $childOptions))
            ->add($this->getMinuteField('minute', $childOptions))
            // Don't modify \DateTime classes by reference, we treat
            // them like immutable value objects
            ->setModifyByReference(false);

        if ($options['with_seconds']) {
            $parts[] = 'second';
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
        } else if ($options['type'] === 'array') {
            $field->setNormalizationTransformer(new ReversedTransformer(
                new DateTimeToArrayTransformer(array(
                    'input_timezone' => $options['data_timezone'],
                    'output_timezone' => $options['data_timezone'],
                    'fields' => $parts,
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
                'fields' => $parts,
            )))
            ->setRendererVar('widget', $options['widget'])
            ->setRendererVar('with_seconds', $options['with_seconds']);

        return $field;
    }

    public function getDateTimeField($key, array $options = array())
    {
        $options = array_merge(array(
            'template' => 'datetime',
            'type' => 'datetime',
            'with_seconds' => false,
            'data_timezone' => date_default_timezone_get(),
            'user_timezone' => date_default_timezone_get(),
        ), $options);

        // Only pass a subset of the options to children
        $dateFieldOptions = array_intersect_key($options, array_flip(array(
            'years',
            'months',
            'days',
        )));
        $timeFieldOptions = array_intersect_key($options, array_flip(array(
            'hours',
            'minutes',
            'seconds',
            'with_seconds',
        )));

        if (isset($options['date_pattern'])) {
            $dateFieldOptions['pattern'] = $options['date_pattern'];
        }
        if (isset($options['date_widget'])) {
            $dateFieldOptions['widget'] = $options['date_widget'];
        }
        if (isset($options['date_format'])) {
            $dateFieldOptions['format'] = $options['date_format'];
        }

        $dateFieldOptions['type'] = 'array';

        if (isset($options['time_pattern'])) {
            $timeFieldOptions['pattern'] = $options['time_pattern'];
        }
        if (isset($options['time_widget'])) {
            $timeFieldOptions['widget'] = $options['time_widget'];
        }
        if (isset($options['time_format'])) {
            $timeFieldOptions['format'] = $options['time_format'];
        }

        $timeFieldOptions['type'] = 'array';

        $parts = array('year', 'month', 'day', 'hour', 'minute');
        $timeParts = array('hour', 'minute');

        if ($options['with_seconds']) {
            $parts[] = 'second';
            $timeParts[] = 'second';
        }

        $field = $this->getForm($key, $options)
            ->setValueTransformer(new ValueTransformerChain(array(
                new DateTimeToArrayTransformer(array(
                    'input_timezone' => $options['data_timezone'],
                    'output_timezone' => $options['user_timezone'],
                    'fields' => $parts,
                )),
                new ArrayToPartsTransformer(array(
                    'date' => array('year', 'month', 'day'),
                    'time' => $timeParts,
                )),
            )))
            ->add($this->getDateField('date', $dateFieldOptions))
            ->add($this->getTimeField('time', $timeFieldOptions))
            // Don't modify \DateTime classes by reference, we treat
            // them like immutable value objects
            ->setModifyByReference(false)
            ->setData(null); // hack: should be invoked automatically

        if ($options['type'] == 'string') {
            $field->setNormalizationTransformer(new ReversedTransformer(
                new DateTimeToStringTransformer(array(
                    'format' => 'Y-m-d H:i:s',
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
        } else if ($options['type'] === 'array') {
            $field->setNormalizationTransformer(new ReversedTransformer(
                new DateTimeToArrayTransformer(array(
                    'input_timezone' => $options['data_timezone'],
                    'output_timezone' => $options['data_timezone'],
                    'fields' => $parts,
                ))
            ));
        }

        return $field;
    }

    public function getRepeatedField($key, array $options = array())
    {
        $options = array_merge(array(
            'template' => 'repeated',
            'first_key' => 'first',
            'second_key' => 'second',
            'prototype' => null,
        ), $options);

        // Lazy creation of the prototype
        if (!isset($options['prototype'])) {
            $options['prototype'] = $this->getTextField('key');
        }

        $firstChild = clone $options['prototype'];
        $firstChild->setKey($options['first_key']);
        $firstChild->setPropertyPath($options['first_key']);

        $secondChild = clone $options['prototype'];
        $secondChild->setKey($options['second_key']);
        $secondChild->setPropertyPath($options['second_key']);

        return $this->getForm($key, $options)
            ->setValueTransformer(new ValueToDuplicatesTransformer(array(
                $options['first_key'],
                $options['second_key'],
            )))
            ->add($firstChild)
            ->add($secondChild);
    }

    public function getFileField($key, array $options = array())
    {
        $options = array_merge(array(
            'template' => 'file',
            'type' => 'string',
        ), $options);

        $field = $this->getForm($key, $options);

        if ($options['type'] === 'string') {
            $field->setNormalizationTransformer(new ValueTransformerChain(array(
                new ReversedTransformer(new FileToStringTransformer()),
                new FileToArrayTransformer(),
            )));
        } else {
            $field->setNormalizationTransformer(new FileToArrayTransformer());
        }

        return $field
            ->prependFilter(new FileUploadFilter($field, $this->storage))
            ->setData(null) // FIXME
            ->add($this->getField('file')->setRendererVar('type', 'file'))
            ->add($this->getHiddenField('token'))
            ->add($this->getHiddenField('name'));
    }

    public function getCollectionField($key, array $options = array())
    {
        $options = array_merge(array(
            'template' => 'collection',
            'prototype' => null,
            'modifiable' => false,
        ), $options);

        $field = $this->getForm($key, $options);

        if (!isset($options['prototype'])) {
            $options['prototype'] = $this->getTextField('prototype');
        }

        if ($options['modifiable']) {
            $child = clone $options['prototype'];
            $child->setKey('$$key$$');
            $child->setPropertyPath(null);
            // TESTME
            $child->setRequired(false);
            $field->add($child);
        }

        $field->addEventListener(new ResizeFormListener($field,
                $options['prototype'], $options['modifiable']));

        return $field;
    }
}