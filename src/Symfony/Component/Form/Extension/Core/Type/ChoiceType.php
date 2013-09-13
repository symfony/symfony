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
use Symfony\Component\Form\Extension\Core\View\ChoiceView;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormView;
use Symfony\Component\Form\Exception\LogicException;
use Symfony\Component\Form\Extension\Core\ChoiceList\SimpleChoiceList;
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
     * Caches created choice lists.
     * @var array
     */
    private $choiceListCache = array();

    /**
     * {@inheritdoc}
     */
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        if (!$options['choice_list'] && !is_array($options['choices']) && !$options['choices'] instanceof \Traversable) {
            throw new LogicException('Either the option "choices" or "choice_list" must be set.');
        }

        if ($options['expanded']) {
            // Initialize all choices before doing the index check below.
            // This helps in cases where index checks are optimized for non
            // initialized choice lists. For example, when using an SQL driver,
            // the index check would read in one SQL query and the initialization
            // requires another SQL query. When the initialization is done first,
            // one SQL query is sufficient.
            $preferredViews = $options['choice_list']->getPreferredViews();
            $remainingViews = $options['choice_list']->getRemainingViews();

            // Check if the choices already contain the empty value
            // Only add the empty value option if this is not the case
            if (null !== $options['empty_value'] && 0 === count($options['choice_list']->getChoicesForValues(array('')))) {
                $placeholderView = new ChoiceView(null, '', $options['empty_value']);

                // "placeholder" is a reserved index
                // see also ChoiceListInterface::getIndicesForChoices()
                $this->addSubForms($builder, array('placeholder' => $placeholderView), $options);
            }

            $this->addSubForms($builder, $preferredViews, $options);
            $this->addSubForms($builder, $remainingViews, $options);

            if ($options['multiple']) {
                $builder->addViewTransformer(new ChoicesToBooleanArrayTransformer($options['choice_list']));
                $builder->addEventSubscriber(new FixCheckboxInputListener($options['choice_list']), 10);
            } else {
                $builder->addViewTransformer(new ChoiceToBooleanArrayTransformer($options['choice_list'], $builder->has('placeholder')));
                $builder->addEventSubscriber(new FixRadioInputListener($options['choice_list'], $builder->has('placeholder')), 10);
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
    public function buildView(FormView $view, FormInterface $form, array $options)
    {
        $view->vars = array_replace($view->vars, array(
            'multiple'          => $options['multiple'],
            'expanded'          => $options['expanded'],
            'preferred_choices' => $options['choice_list']->getPreferredViews(),
            'choices'           => $options['choice_list']->getRemainingViews(),
            'separator'         => '-------------------',
            'empty_value'       => null,
        ));

        // The decision, whether a choice is selected, is potentially done
        // thousand of times during the rendering of a template. Provide a
        // closure here that is optimized for the value of the form, to
        // avoid making the type check inside the closure.
        if ($options['multiple']) {
            $view->vars['is_selected'] = function ($choice, array $values) {
                return false !== array_search($choice, $values, true);
            };
        } else {
            $view->vars['is_selected'] = function ($choice, $value) {
                return $choice === $value;
            };
        }

        // Check if the choices already contain the empty value
        $view->vars['empty_value_in_choices'] = 0 !== count($options['choice_list']->getChoicesForValues(array('')));

        // Only add the empty value option if this is not the case
        if (null !== $options['empty_value'] && !$view->vars['empty_value_in_choices']) {
            $view->vars['empty_value'] = $options['empty_value'];
        }

        if ($options['multiple'] && !$options['expanded']) {
            // Add "[]" to the name in case a select tag with multiple options is
            // displayed. Otherwise only one of the selected options is sent in the
            // POST request.
            $view->vars['full_name'] = $view->vars['full_name'].'[]';
        }
    }

    /**
     * {@inheritdoc}
     */
    public function finishView(FormView $view, FormInterface $form, array $options)
    {
        if ($options['expanded']) {
            // Radio buttons should have the same name as the parent
            $childName = $view->vars['full_name'];

            // Checkboxes should append "[]" to allow multiple selection
            if ($options['multiple']) {
                $childName .= '[]';
            }

            foreach ($view as $childView) {
                $childView->vars['full_name'] = $childName;
            }
        }
    }

    /**
     * {@inheritdoc}
     */
    public function setDefaultOptions(OptionsResolverInterface $resolver)
    {
        $choiceListCache =& $this->choiceListCache;

        $choiceList = function (Options $options) use (&$choiceListCache) {
            // Harden against NULL values (like in EntityType and ModelType)
            $choices = null !== $options['choices'] ? $options['choices'] : array();

            // Reuse existing choice lists in order to increase performance
            $hash = md5(json_encode(array($choices, $options['preferred_choices'])));

            if (!isset($choiceListCache[$hash])) {
                $choiceListCache[$hash] = new SimpleChoiceList($choices, $options['preferred_choices']);
            }

            return $choiceListCache[$hash];
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

        $emptyValueNormalizer = function (Options $options, $emptyValue) {
            if ($options['multiple']) {
                // never use an empty value for this case
                return null;
            } elseif (false === $emptyValue) {
                // an empty value should be added but the user decided otherwise
                return null;
            } elseif ($options['expanded'] && '' === $emptyValue) {
                // never use an empty label for radio buttons
                return 'None';
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
            // The view data is always a string, even if the "data" option
            // is manually set to an object.
            // See https://github.com/symfony/symfony/pull/5582
            'data_class'        => null,
        ));

        $resolver->setNormalizers(array(
            'empty_value' => $emptyValueNormalizer,
        ));

        $resolver->setAllowedTypes(array(
            'choice_list' => array('null', 'Symfony\Component\Form\Extension\Core\ChoiceList\ChoiceListInterface'),
        ));
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
                    'value' => $choiceView->value,
                    'label' => $choiceView->label,
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

                $builder->add($i, $choiceType, $choiceOpts);
            }
        }
    }
}
