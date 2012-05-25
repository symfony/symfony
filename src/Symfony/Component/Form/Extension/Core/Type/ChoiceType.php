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
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormViewInterface;
use Symfony\Component\Form\Exception\FormException;
use Symfony\Component\Form\Extension\Core\ChoiceList\ChoiceList;
use Symfony\Component\Form\Extension\Core\ChoiceList\SimpleChoiceList;
use Symfony\Component\Form\Extension\Core\ChoiceList\ChoiceListInterface;
use Symfony\Component\Form\Extension\Core\EventListener\FixRadioInputListener;
use Symfony\Component\Form\Extension\Core\EventListener\FixCheckboxInputListener;
use Symfony\Component\Form\Extension\Core\EventListener\MergeCollectionListener;
use Symfony\Component\Form\Extension\Core\DataTransformer\ChoiceToValueTransformer;
use Symfony\Component\Form\Extension\Core\DataTransformer\ChoiceToBooleanArrayTransformer;
use Symfony\Component\Form\Extension\Core\DataTransformer\ChoicesToValuesTransformer;
use Symfony\Component\Form\Extension\Core\DataTransformer\ChoicesToBooleanArrayTransformer;
use Symfony\Component\OptionsResolver\Options;
use Symfony\Component\OptionsResolver\OptionsResolverInterface;

class ChoiceType extends AbstractType
{
    /**
     * {@inheritdoc}
     */
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        if (!$options['choice_list'] && !is_array($options['choices']) && !$options['choices'] instanceof \Traversable) {
            throw new FormException('Either the option "choices" or "choice_list" must be set.');
        }

        if ($options['expanded']) {
            $this->addSubForms($builder, $options['choice_list']->getPreferredViews(), $options);
            $this->addSubForms($builder, $options['choice_list']->getRemainingViews(), $options);

            if ($options['multiple']) {
                $builder
                    ->addViewTransformer(new ChoicesToBooleanArrayTransformer($options['choice_list']))
                    ->addEventSubscriber(new FixCheckboxInputListener($options['choice_list']), 10)
                ;
            } else {
                $builder
                    ->addViewTransformer(new ChoiceToBooleanArrayTransformer($options['choice_list']))
                    ->addEventSubscriber(new FixRadioInputListener($options['choice_list']), 10)
                ;
            }
        } else {
            if ($options['multiple']) {
                $builder->addViewTransformer(new ChoicesToValuesTransformer($options['choice_list']));
            } else {
                $builder->addViewTransformer(new ChoiceToValueTransformer($options['choice_list']));
            }
        }

        if ($options['multiple'] && $options['by_reference']) {
            // Make sure the collection created during the client->norm
            // transformation is merged back into the original collection
            $builder->addEventSubscriber(new MergeCollectionListener(true, true));
        }
    }

    /**
     * {@inheritdoc}
     */
    public function buildView(FormViewInterface $view, FormInterface $form, array $options)
    {
        $view->addVars(array(
            'multiple'          => $options['multiple'],
            'expanded'          => $options['expanded'],
            'preferred_choices' => $options['choice_list']->getPreferredViews(),
            'choices'           => $options['choice_list']->getRemainingViews(),
            'separator'         => '-------------------',
            'empty_value'       => $options['empty_value'],
        ));

        if ($options['multiple'] && !$options['expanded']) {
            // Add "[]" to the name in case a select tag with multiple options is
            // displayed. Otherwise only one of the selected options is sent in the
            // POST request.
            $view->setVar('full_name', $view->getVar('full_name').'[]');
        }
    }

    /**
     * {@inheritdoc}
     */
    public function finishView(FormViewInterface $view, FormInterface $form, array $options)
    {
        if ($options['expanded']) {
            // Radio buttons should have the same name as the parent
            $childName = $view->getVar('full_name');

            // Checkboxes should append "[]" to allow multiple selection
            if ($options['multiple']) {
                $childName .= '[]';
            }

            foreach ($view as $childView) {
                $childView->setVar('full_name', $childName);
            }
        }
    }

    /**
     * {@inheritdoc}
     */
    public function setDefaultOptions(OptionsResolverInterface $resolver)
    {
        $choiceList = function (Options $options) {
            return new SimpleChoiceList(
                // Harden against NULL values (like in EntityType and ModelType)
                null !== $options['choices'] ? $options['choices'] : array(),
                $options['preferred_choices']
            );
        };

        $emptyData = function (Options $options) {
            if ($options['multiple'] || $options['expanded']) {
                return array();
            }

            return '';
        };

        $emptyValue = function (Options $options) {
            return $options['required'] ? null : '';
        };

        $emptyValueFilter = function (Options $options, $emptyValue) {
            if ($options['multiple'] || $options['expanded']) {
                // never use an empty value for these cases
                return null;
            } elseif (false === $emptyValue) {
                // an empty value should be added but the user decided otherwise
                return null;
            }

            // empty value has been set explicitly
            return $emptyValue;
        };

        $compound = function (Options $options) {
            return $options['expanded'];
        };

        $resolver->setDefaults(array(
            'multiple'          => false,
            'expanded'          => false,
            'choice_list'       => $choiceList,
            'choices'           => array(),
            'preferred_choices' => array(),
            'empty_data'        => $emptyData,
            'empty_value'       => $emptyValue,
            'error_bubbling'    => false,
            'compound'          => $compound,
        ));

        $resolver->setFilters(array(
            'empty_value' => $emptyValueFilter,
        ));

        $resolver->setAllowedTypes(array(
            'choice_list' => array('null', 'Symfony\Component\Form\Extension\Core\ChoiceList\ChoiceListInterface'),
        ));
    }

    /**
     * {@inheritdoc}
     */
    public function getParent()
    {
        return 'field';
    }

    /**
     * {@inheritdoc}
     */
    public function getName()
    {
        return 'choice';
    }

    /**
     * Adds the sub fields for an expanded choice field.
     *
     * @param FormBuilderInterface $builder     The form builder.
     * @param array                $choiceViews The choice view objects.
     * @param array                $options     The build options.
     */
    private function addSubForms(FormBuilderInterface $builder, array $choiceViews, array $options)
    {
        foreach ($choiceViews as $i => $choiceView) {
            if (is_array($choiceView)) {
                // Flatten groups
                $this->addSubForms($builder, $choiceView, $options);
            } else {
                $choiceOpts = array(
                    'value' => $choiceView->getValue(),
                    'label' => $choiceView->getLabel(),
                    'translation_domain' => $options['translation_domain'],
                );

                if ($options['multiple']) {
                    $choiceType = 'checkbox';
                    // The user can check 0 or more checkboxes. If required
                    // is true, he is required to check all of them.
                    $choiceOpts['required'] = false;
                } else {
                    $choiceType = 'radio';
                }

                $builder->add((string) $i, $choiceType, $choiceOpts);
            }
        }
    }
}
