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
use Symfony\Component\Form\Exception\LogicException;
use Symfony\Component\Form\Extension\Core\DataTransformer\WeekToArrayTransformer;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormView;
use Symfony\Component\Form\ReversedTransformer;
use Symfony\Component\OptionsResolver\Options;
use Symfony\Component\OptionsResolver\OptionsResolver;

class WeekType extends AbstractType
{
    private const WIDGETS = [
        'text' => IntegerType::class,
        'choice' => ChoiceType::class,
    ];

    /**
     * {@inheritdoc}
     */
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        if ('string' === $options['input']) {
            $builder->addModelTransformer(new WeekToArrayTransformer());
        }

        if ('single_text' === $options['widget']) {
            $builder->addViewTransformer(new ReversedTransformer(new WeekToArrayTransformer()));
        } else {
            $yearOptions = $weekOptions = [
                'error_bubbling' => true,
                'empty_data' => '',
            ];
            // when the form is compound the entries of the array are ignored in favor of children data
            // so we need to handle the cascade setting here
            $emptyData = $builder->getEmptyData() ?: [];

            $yearOptions['empty_data'] = $emptyData['year'] ?? '';
            $weekOptions['empty_data'] = $emptyData['week'] ?? '';

            if (isset($options['invalid_message'])) {
                $yearOptions['invalid_message'] = $options['invalid_message'];
                $weekOptions['invalid_message'] = $options['invalid_message'];
            }

            if (isset($options['invalid_message_parameters'])) {
                $yearOptions['invalid_message_parameters'] = $options['invalid_message_parameters'];
                $weekOptions['invalid_message_parameters'] = $options['invalid_message_parameters'];
            }

            if ('choice' === $options['widget']) {
                // Only pass a subset of the options to children
                $yearOptions['choices'] = array_combine($options['years'], $options['years']);
                $yearOptions['placeholder'] = $options['placeholder']['year'];
                $yearOptions['choice_translation_domain'] = $options['choice_translation_domain']['year'];

                $weekOptions['choices'] = array_combine($options['weeks'], $options['weeks']);
                $weekOptions['placeholder'] = $options['placeholder']['week'];
                $weekOptions['choice_translation_domain'] = $options['choice_translation_domain']['week'];

                // Append generic carry-along options
                foreach (['required', 'translation_domain'] as $passOpt) {
                    $yearOptions[$passOpt] = $options[$passOpt];
                    $weekOptions[$passOpt] = $options[$passOpt];
                }
            }

            $builder->add('year', self::WIDGETS[$options['widget']], $yearOptions);
            $builder->add('week', self::WIDGETS[$options['widget']], $weekOptions);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function buildView(FormView $view, FormInterface $form, array $options)
    {
        $view->vars['widget'] = $options['widget'];

        if ($options['html5']) {
            $view->vars['type'] = 'week';
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

        $placeholderDefault = function (Options $options) {
            return $options['required'] ? null : '';
        };

        $placeholderNormalizer = function (Options $options, $placeholder) use ($placeholderDefault) {
            if (\is_array($placeholder)) {
                $default = $placeholderDefault($options);

                return array_merge(
                    ['year' => $default, 'week' => $default],
                    $placeholder
                );
            }

            return [
                'year' => $placeholder,
                'week' => $placeholder,
            ];
        };

        $choiceTranslationDomainNormalizer = function (Options $options, $choiceTranslationDomain) {
            if (\is_array($choiceTranslationDomain)) {
                $default = false;

                return array_replace(
                    ['year' => $default, 'week' => $default],
                    $choiceTranslationDomain
                );
            }

            return [
                'year' => $choiceTranslationDomain,
                'week' => $choiceTranslationDomain,
            ];
        };

        $resolver->setDefaults([
            'years' => range(date('Y') - 10, date('Y') + 10),
            'weeks' => array_combine(range(1, 53), range(1, 53)),
            'widget' => 'single_text',
            'input' => 'array',
            'placeholder' => $placeholderDefault,
            'html5' => static function (Options $options) {
                return 'single_text' === $options['widget'];
            },
            'error_bubbling' => false,
            'empty_data' => function (Options $options) {
                return $options['compound'] ? [] : '';
            },
            'compound' => $compound,
            'choice_translation_domain' => false,
            'invalid_message' => static function (Options $options, $previousValue) {
                return ($options['legacy_error_messages'] ?? true) ? $previousValue : 'Please enter a valid week.';
            },
        ]);

        $resolver->setNormalizer('placeholder', $placeholderNormalizer);
        $resolver->setNormalizer('choice_translation_domain', $choiceTranslationDomainNormalizer);
        $resolver->setNormalizer('html5', function (Options $options, $html5) {
            if ($html5 && 'single_text' !== $options['widget']) {
                throw new LogicException(sprintf('The "widget" option of "%s" must be set to "single_text" when the "html5" option is enabled.', self::class));
            }

            return $html5;
        });

        $resolver->setAllowedValues('input', [
            'string',
            'array',
        ]);

        $resolver->setAllowedValues('widget', [
            'single_text',
            'text',
            'choice',
        ]);

        $resolver->setAllowedTypes('years', 'int[]');
        $resolver->setAllowedTypes('weeks', 'int[]');
    }

    /**
     * {@inheritdoc}
     */
    public function getBlockPrefix()
    {
        return 'week';
    }
}
