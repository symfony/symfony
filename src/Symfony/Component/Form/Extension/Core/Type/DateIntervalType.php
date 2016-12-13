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
use Symfony\Component\Form\Exception\InvalidConfigurationException;
use Symfony\Component\Form\Extension\Core\DataTransformer\DateIntervalToArrayTransformer;
use Symfony\Component\Form\Extension\Core\DataTransformer\DateIntervalToStringTransformer;
use Symfony\Component\Form\Extension\Core\DataTransformer\IntegerToLocalizedStringTransformer;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormView;
use Symfony\Component\Form\ReversedTransformer;
use Symfony\Component\OptionsResolver\Options;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * @author Steffen Roßkamp <steffen.rosskamp@gimmickmedia.de>
 */
class DateIntervalType extends AbstractType
{
    private $timeParts = array(
        'years',
        'months',
        'weeks',
        'days',
        'hours',
        'minutes',
        'seconds',
    );
    private static $widgets = array(
        'text' => 'Symfony\Component\Form\Extension\Core\Type\TextType',
        'integer' => 'Symfony\Component\Form\Extension\Core\Type\IntegerType',
        'choice' => 'Symfony\Component\Form\Extension\Core\Type\ChoiceType',
    );

    /**
     * {@inheritdoc}
     */
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        if (!$options['with_years'] && !$options['with_months'] && !$options['with_weeks'] && !$options['with_days'] && !$options['with_hours'] && !$options['with_minutes'] && !$options['with_seconds']) {
            throw new InvalidConfigurationException('You must enable at least one interval field.');
        }
        if ($options['with_invert'] && 'single_text' === $options['widget']) {
            throw new InvalidConfigurationException('The single_text widget does not support invertible intervals.');
        }
        if ($options['with_weeks'] && $options['with_days']) {
            throw new InvalidConfigurationException('You can not enable weeks and days fields together.');
        }
        $format = 'P';
        $parts = array();
        if ($options['with_years']) {
            $format .= '%yY';
            $parts[] = 'years';
        }
        if ($options['with_months']) {
            $format .= '%mM';
            $parts[] = 'months';
        }
        if ($options['with_weeks']) {
            $format .= '%wW';
            $parts[] = 'weeks';
        }
        if ($options['with_days']) {
            $format .= '%dD';
            $parts[] = 'days';
        }
        if ($options['with_hours'] || $options['with_minutes'] || $options['with_seconds']) {
            $format .= 'T';
        }
        if ($options['with_hours']) {
            $format .= '%hH';
            $parts[] = 'hours';
        }
        if ($options['with_minutes']) {
            $format .= '%iM';
            $parts[] = 'minutes';
        }
        if ($options['with_seconds']) {
            $format .= '%sS';
            $parts[] = 'seconds';
        }
        if ($options['with_invert']) {
            $parts[] = 'invert';
        }
        if ('single_text' === $options['widget']) {
            $builder->addViewTransformer(new DateIntervalToStringTransformer($format));
        } else {
            $childOptions = array();
            foreach ($this->timeParts as $part) {
                if ($options['with_'.$part]) {
                    $childOptions[$part] = array();
                    $childOptions[$part]['error_bubbling'] = true;
                    if ('choice' === $options['widget']) {
                        $childOptions[$part]['choice_translation_domain'] = false;
                        $childOptions[$part]['choices'] = $options[$part];
                        $childOptions[$part]['placeholder'] = $options['placeholder'][$part];
                    }
                }
            }
            // Append generic carry-along options
            foreach (array('required', 'translation_domain') as $passOpt) {
                foreach ($this->timeParts as $part) {
                    if ($options['with_'.$part]) {
                        $childOptions[$part][$passOpt] = $options[$passOpt];
                    }
                }
            }
            foreach ($this->timeParts as $part) {
                if ($options['with_'.$part]) {
                    $childForm = $builder->create($part, self::$widgets[$options['widget']], $childOptions[$part]);
                    if ('integer' === $options['widget']) {
                        $childForm->addModelTransformer(
                            new ReversedTransformer(
                                new IntegerToLocalizedStringTransformer()
                            )
                        );
                    }
                    $builder->add($childForm);
                }
            }
            if ($options['with_invert']) {
                $builder->add('invert', 'Symfony\Component\Form\Extension\Core\Type\CheckboxType', array(
                    'error_bubbling' => true,
                    'required' => false,
                    'translation_domain' => $options['translation_domain'],
                ));
            }
            $builder->addViewTransformer(new DateIntervalToArrayTransformer($parts, 'text' === $options['widget']));
        }
        if ('string' === $options['input']) {
            $builder->addModelTransformer(
                new ReversedTransformer(
                    new DateIntervalToStringTransformer($format)
                )
            );
        } elseif ('array' === $options['input']) {
            $builder->addModelTransformer(
                new ReversedTransformer(
                    new DateIntervalToArrayTransformer($parts)
                )
            );
        }
    }

    /**
     * {@inheritdoc}
     */
    public function buildView(FormView $view, FormInterface $form, array $options)
    {
        $vars = array(
            'widget' => $options['widget'],
            'with_invert' => $options['with_invert'],
        );
        foreach ($this->timeParts as $part) {
            $vars['with_'.$part] = $options['with_'.$part];
        }
        $view->vars = array_replace($view->vars, $vars);
    }

    /**
     * {@inheritdoc}
     */
    public function configureOptions(OptionsResolver $resolver)
    {
        $timeParts = $this->timeParts;
        $compound = function (Options $options) {
            return $options['widget'] !== 'single_text';
        };

        $placeholderDefault = function (Options $options) {
            return $options['required'] ? null : '';
        };

        $placeholderNormalizer = function (Options $options, $placeholder) use ($placeholderDefault, $timeParts) {
            if (is_array($placeholder)) {
                $default = $placeholderDefault($options);

                return array_merge(array_fill_keys($timeParts, $default), $placeholder);
            }

            return array_fill_keys($timeParts, $placeholder);
        };

        $resolver->setDefaults(
            array(
                'with_years' => true,
                'with_months' => true,
                'with_days' => true,
                'with_weeks' => false,
                'with_hours' => false,
                'with_minutes' => false,
                'with_seconds' => false,
                'with_invert' => false,
                'years' => range(0, 100),
                'months' => range(0, 12),
                'weeks' => range(0, 52),
                'days' => range(0, 31),
                'hours' => range(0, 24),
                'minutes' => range(0, 60),
                'seconds' => range(0, 60),
                'widget' => 'choice',
                'input' => 'dateinterval',
                'placeholder' => $placeholderDefault,
                'by_reference' => true,
                'error_bubbling' => false,
                // If initialized with a \DateInterval object, FormType initializes
                // this option to "\DateInterval". Since the internal, normalized
                // representation is not \DateInterval, but an array, we need to unset
                // this option.
                'data_class' => null,
                'compound' => $compound,
            )
        );
        $resolver->setNormalizer('placeholder', $placeholderNormalizer);

        $resolver->setAllowedValues(
            'input',
            array(
                'dateinterval',
                'string',
                'array',
            )
        );
        $resolver->setAllowedValues(
            'widget',
            array(
                'single_text',
                'text',
                'integer',
                'choice',
            )
        );
        // Don't clone \DateInterval classes, as i.e. format()
        // does not work after that
        $resolver->setAllowedValues('by_reference', true);

        $resolver->setAllowedTypes('years', 'array');
        $resolver->setAllowedTypes('months', 'array');
        $resolver->setAllowedTypes('weeks', 'array');
        $resolver->setAllowedTypes('days', 'array');
        $resolver->setAllowedTypes('hours', 'array');
        $resolver->setAllowedTypes('minutes', 'array');
        $resolver->setAllowedTypes('seconds', 'array');
        $resolver->setAllowedTypes('with_years', 'bool');
        $resolver->setAllowedTypes('with_months', 'bool');
        $resolver->setAllowedTypes('with_weeks', 'bool');
        $resolver->setAllowedTypes('with_days', 'bool');
        $resolver->setAllowedTypes('with_hours', 'bool');
        $resolver->setAllowedTypes('with_minutes', 'bool');
        $resolver->setAllowedTypes('with_seconds', 'bool');
        $resolver->setAllowedTypes('with_invert', 'bool');
    }

    /**
     * {@inheritdoc}
     */
    public function getBlockPrefix()
    {
        return 'dateinterval';
    }
}
