<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Form\Extension\Core\Type;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\ReversedTransformer;
use Symfony\Component\Form\Exception\InvalidConfigurationException;
use Symfony\Component\Form\Extension\Core\DataTransformer\DateTimeToStringTransformer;
use Symfony\Component\Form\Extension\Core\DataTransformer\DateTimeToTimestampTransformer;
use Symfony\Component\Form\Extension\Core\DataTransformer\DateTimeToArrayTransformer;
use Symfony\Component\Form\FormView;
use Symfony\Component\OptionsResolver\Options;
use Symfony\Component\OptionsResolver\OptionsResolver;

class TimeType extends AbstractType
{
    private static $widgets = array(
        'text' => 'Symfony\Component\Form\Extension\Core\Type\TextType',
        'choice' => 'Symfony\Component\Form\Extension\Core\Type\ChoiceType',
    );

    /**
     * {@inheritdoc}
     */
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $parts = array('hour');
        $format = 'H';

        if ($options['with_seconds'] && !$options['with_minutes']) {
            throw new InvalidConfigurationException('You can not disable minutes if you have enabled seconds.');
        }

        if ($options['with_minutes']) {
            $format .= ':i';
            $parts[] = 'minute';
        }

        if ($options['with_seconds']) {
            $format .= ':s';
            $parts[] = 'second';
        }

        if ('single_text' === $options['widget']) {
            $builder->addViewTransformer(new DateTimeToStringTransformer($options['model_timezone'], $options['view_timezone'], $format));

            // handle seconds ignored by user's browser when with_seconds enabled
            // https://codereview.chromium.org/450533009/
            if ($options['with_seconds']) {
                $builder->addEventListener(FormEvents::PRE_SUBMIT, function (FormEvent $e) {
                    $data = $e->getData();
                    if ($data && preg_match('/^\d{2}:\d{2}$/', $data)) {
                        $e->setData($data.':00');
                    }
                });
            }
        } else {
            $hourOptions = $minuteOptions = $secondOptions = array(
                'error_bubbling' => true,
            );

            if ('choice' === $options['widget']) {
                $hours = $minutes = array();

                foreach ($options['hours'] as $hour) {
                    $hours[str_pad($hour, 2, '0', STR_PAD_LEFT)] = $hour;
                }

                // Only pass a subset of the options to children
                $hourOptions['choices'] = $hours;
                $hourOptions['choices_as_values'] = true;
                $hourOptions['placeholder'] = $options['placeholder']['hour'];
                $hourOptions['choice_translation_domain'] = $options['choice_translation_domain']['hour'];

                if ($options['with_minutes']) {
                    foreach ($options['minutes'] as $minute) {
                        $minutes[str_pad($minute, 2, '0', STR_PAD_LEFT)] = $minute;
                    }

                    $minuteOptions['choices'] = $minutes;
                    $minuteOptions['choices_as_values'] = true;
                    $minuteOptions['placeholder'] = $options['placeholder']['minute'];
                    $minuteOptions['choice_translation_domain'] = $options['choice_translation_domain']['minute'];
                }

                if ($options['with_seconds']) {
                    $seconds = array();

                    foreach ($options['seconds'] as $second) {
                        $seconds[str_pad($second, 2, '0', STR_PAD_LEFT)] = $second;
                    }

                    $secondOptions['choices'] = $seconds;
                    $secondOptions['choices_as_values'] = true;
                    $secondOptions['placeholder'] = $options['placeholder']['second'];
                    $secondOptions['choice_translation_domain'] = $options['choice_translation_domain']['second'];
                }

                // Append generic carry-along options
                foreach (array('required', 'translation_domain') as $passOpt) {
                    $hourOptions[$passOpt] = $options[$passOpt];

                    if ($options['with_minutes']) {
                        $minuteOptions[$passOpt] = $options[$passOpt];
                    }

                    if ($options['with_seconds']) {
                        $secondOptions[$passOpt] = $options[$passOpt];
                    }
                }
            }

            $builder->add('hour', self::$widgets[$options['widget']], $hourOptions);

            if ($options['with_minutes']) {
                $builder->add('minute', self::$widgets[$options['widget']], $minuteOptions);
            }

            if ($options['with_seconds']) {
                $builder->add('second', self::$widgets[$options['widget']], $secondOptions);
            }

            $builder->addViewTransformer(new DateTimeToArrayTransformer($options['model_timezone'], $options['view_timezone'], $parts, 'text' === $options['widget']));
        }

        if ('string' === $options['input']) {
            $builder->addModelTransformer(new ReversedTransformer(
                new DateTimeToStringTransformer($options['model_timezone'], $options['model_timezone'], 'H:i:s')
            ));
        } elseif ('timestamp' === $options['input']) {
            $builder->addModelTransformer(new ReversedTransformer(
                new DateTimeToTimestampTransformer($options['model_timezone'], $options['model_timezone'])
            ));
        } elseif ('array' === $options['input']) {
            $builder->addModelTransformer(new ReversedTransformer(
                new DateTimeToArrayTransformer($options['model_timezone'], $options['model_timezone'], $parts)
            ));
        }
    }

    /**
     * {@inheritdoc}
     */
    public function buildView(FormView $view, FormInterface $form, array $options)
    {
        $view->vars = array_replace($view->vars, array(
            'widget' => $options['widget'],
            'with_minutes' => $options['with_minutes'],
            'with_seconds' => $options['with_seconds'],
        ));

        // Change the input to a HTML5 time input if
        //  * the widget is set to "single_text"
        //  * the html5 is set to true
        if ($options['html5'] && 'single_text' === $options['widget']) {
            $view->vars['type'] = 'time';

            // we need to force the browser to display the seconds by
            // adding the HTML attribute step if not already defined.
            // Otherwise the browser will not display and so not send the seconds
            // therefore the value will always be considered as invalid.
            if ($options['with_seconds'] && !isset($view->vars['attr']['step'])) {
                $view->vars['attr']['step'] = 1;
            }
        }
    }

    /**
     * {@inheritdoc}
     */
    public function configureOptions(OptionsResolver $resolver)
    {
        $compound = function (Options $options) {
            return 'single_text' !== $options['widget'];
        };

        $placeholder = $placeholderDefault = function (Options $options) {
            return $options['required'] ? null : '';
        };

        $placeholderNormalizer = function (Options $options, $placeholder) use ($placeholderDefault) {
            if (!is_object($options['empty_value']) || !$options['empty_value'] instanceof \Exception) {
                @trigger_error('The form option "empty_value" is deprecated since version 2.6 and will be removed in 3.0. Use "placeholder" instead.', E_USER_DEPRECATED);

                $placeholder = $options['empty_value'];
            }

            if (is_array($placeholder)) {
                $default = $placeholderDefault($options);

                return array_merge(
                    array('hour' => $default, 'minute' => $default, 'second' => $default),
                    $placeholder
                );
            }

            return array(
                'hour' => $placeholder,
                'minute' => $placeholder,
                'second' => $placeholder,
            );
        };

        $choiceTranslationDomainNormalizer = function (Options $options, $choiceTranslationDomain) {
            if (is_array($choiceTranslationDomain)) {
                $default = false;

                return array_replace(
                    array('hour' => $default, 'minute' => $default, 'second' => $default),
                    $choiceTranslationDomain
                );
            };

            return array(
                'hour' => $choiceTranslationDomain,
                'minute' => $choiceTranslationDomain,
                'second' => $choiceTranslationDomain,
            );
        };

        $resolver->setDefaults(array(
            'hours' => range(0, 23),
            'minutes' => range(0, 59),
            'seconds' => range(0, 59),
            'widget' => 'choice',
            'input' => 'datetime',
            'with_minutes' => true,
            'with_seconds' => false,
            'model_timezone' => null,
            'view_timezone' => null,
            'empty_value' => new \Exception(), // deprecated
            'placeholder' => $placeholder,
            'html5' => true,
            // Don't modify \DateTime classes by reference, we treat
            // them like immutable value objects
            'by_reference' => false,
            'error_bubbling' => false,
            // If initialized with a \DateTime object, FormType initializes
            // this option to "\DateTime". Since the internal, normalized
            // representation is not \DateTime, but an array, we need to unset
            // this option.
            'data_class' => null,
            'compound' => $compound,
            'choice_translation_domain' => false,
        ));

        $resolver->setNormalizer('placeholder', $placeholderNormalizer);
        $resolver->setNormalizer('choice_translation_domain', $choiceTranslationDomainNormalizer);

        $resolver->setAllowedValues('input', array(
            'datetime',
            'string',
            'timestamp',
            'array',
        ));
        $resolver->setAllowedValues('widget', array(
            'single_text',
            'text',
            'choice',
        ));

        $resolver->setAllowedTypes('hours', 'array');
        $resolver->setAllowedTypes('minutes', 'array');
        $resolver->setAllowedTypes('seconds', 'array');
    }

    /**
     * {@inheritdoc}
     */
    public function getName()
    {
        return $this->getBlockPrefix();
    }

    /**
     * {@inheritdoc}
     */
    public function getBlockPrefix()
    {
        return 'time';
    }
}
