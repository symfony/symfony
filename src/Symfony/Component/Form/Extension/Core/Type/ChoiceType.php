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
use Symfony\Component\Form\Options;
use Symfony\Component\Form\FormBuilder;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormView;
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

class ChoiceType extends AbstractType
{
    /**
     * {@inheritdoc}
     */
    public function buildForm(FormBuilder $builder, array $options)
    {
        if ($options['choice_list'] && !$options['choice_list'] instanceof ChoiceListInterface) {
            throw new FormException('The "choice_list" must be an instance of "Symfony\Component\Form\Extension\Core\ChoiceList\ChoiceListInterface".');
        }

        if (!$options['choice_list'] && !is_array($options['choices']) && !$options['choices'] instanceof \Traversable) {
            throw new FormException('Either the option "choices" or "choice_list" must be set.');
        }

        if ($options['expanded']) {
            $this->addSubForms($builder, $options['choice_list']->getPreferredViews(), $options);
            $this->addSubForms($builder, $options['choice_list']->getRemainingViews(), $options);
        }

        // empty value
        if ($options['multiple'] || $options['expanded']) {
            // never use and empty value for these cases
            $emptyValue = null;
        } elseif (false === $options['empty_value']) {
            // an empty value should be added but the user decided otherwise
            $emptyValue = null;
        } else {
            // empty value has been set explicitly
            $emptyValue = $options['empty_value'];
        }

        $builder
            ->setAttribute('choice_list', $options['choice_list'])
            ->setAttribute('preferred_choices', $options['preferred_choices'])
            ->setAttribute('multiple', $options['multiple'])
            ->setAttribute('expanded', $options['expanded'])
            ->setAttribute('required', $options['required'])
            ->setAttribute('empty_value', $emptyValue)
        ;

        if ($options['expanded']) {
            if ($options['multiple']) {
                $builder
                    ->appendClientTransformer(new ChoicesToBooleanArrayTransformer($options['choice_list']))
                    ->addEventSubscriber(new FixCheckboxInputListener($options['choice_list']), 10)
                ;
            } else {
                $builder
                    ->appendClientTransformer(new ChoiceToBooleanArrayTransformer($options['choice_list']))
                    ->addEventSubscriber(new FixRadioInputListener($options['choice_list']), 10)
                ;
            }
        } else {
            if ($options['multiple']) {
                $builder->appendClientTransformer(new ChoicesToValuesTransformer($options['choice_list']));
            } else {
                $builder->appendClientTransformer(new ChoiceToValueTransformer($options['choice_list']));
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
    public function buildView(FormView $view, FormInterface $form)
    {
        $choiceList = $form->getAttribute('choice_list');

        $view
            ->set('multiple', $form->getAttribute('multiple'))
            ->set('expanded', $form->getAttribute('expanded'))
            ->set('preferred_choices', $choiceList->getPreferredViews())
            ->set('choices', $choiceList->getRemainingViews())
            ->set('separator', '-------------------')
            ->set('empty_value', $form->getAttribute('empty_value'))
        ;

        if ($view->get('multiple') && !$view->get('expanded')) {
            // Add "[]" to the name in case a select tag with multiple options is
            // displayed. Otherwise only one of the selected options is sent in the
            // POST request.
            $view->set('full_name', $view->get('full_name').'[]');
        }
    }

    /**
     * {@inheritdoc}
     */
    public function buildViewBottomUp(FormView $view, FormInterface $form)
    {
        if ($view->get('expanded')) {
            // Radio buttons should have the same name as the parent
            $childName = $view->get('full_name');

            // Checkboxes should append "[]" to allow multiple selection
            if ($view->get('multiple')) {
                $childName .= '[]';
            }

            foreach ($view->getChildren() as $childView) {
                $childView->set('full_name', $childName);
            }
        }
    }

    /**
     * {@inheritdoc}
     */
    public function getDefaultOptions()
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

        $singleControl = function (Options $options) {
            return !$options['expanded'];
        };

        return array(
            'multiple'          => false,
            'expanded'          => false,
            'choice_list'       => $choiceList,
            'choices'           => array(),
            'preferred_choices' => array(),
            'empty_data'        => $emptyData,
            'empty_value'       => $emptyValue,
            'error_bubbling'    => false,
            'single_control'         => $singleControl,
        );
    }

    /**
     * {@inheritdoc}
     */
    public function getParent(array $options)
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
     * @param FormBuilder $builder The form builder.
     * @param array $choiceViews The choice view objects.
     * @param array $options The build options.
     */
    private function addSubForms(FormBuilder $builder, array $choiceViews, array $options)
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
